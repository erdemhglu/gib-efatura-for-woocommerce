<?php
defined( 'ABSPATH' ) || exit;

use Mlevent\Fatura\Exceptions\FaturaException;

/**
 * Fatura oluşturma / imzalama / indirme / silme akışlarını yöneten orkestrasyon katmanı.
 * Sipariş oluşturma (WGF_Order_Builder), GİB API (WGF_Gib_Service) ve
 * kayıt (WGF_Invoice_Repository) katmanlarını bir araya getirir.
 */
class WGF_Invoice_Service {

	/**
	 * Bir sipariş için yeni GİB fatura taslağı oluşturur.
	 * Siparişte zaten aktif (taslak/imzalanmış) bir fatura varsa hata verir.
	 *
	 * @throws WGF_Exception
	 */
	public static function create_invoice( int $order_id, array $overrides = [] ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'woo-gib-efatura' ) );
		}

		$existing = WGF_Invoice_Repository::find_active_by_order( $order_id );
		if ( $existing ) {
			throw new WGF_Exception(
				sprintf(
					/* translators: %s: mevcut fatura belge no ya da durumu */
					__( 'Bu sipariş için zaten bir GİB faturası oluşturulmuş (%s). Mükerrer fatura oluşturulamaz. Gerekiyorsa önce mevcut taslağı silin.', 'woo-gib-efatura' ),
					$existing['belge_no'] ?: ( 'taslak, UUID: ' . $existing['uuid'] )
				)
			);
		}

		try {
			$model = WGF_Order_Builder::build( $order, $overrides );
		} catch ( FaturaException $e ) {
			throw new WGF_Exception( $e->getMessage() );
		}

		$uuid = WGF_Gib_Service::create_draft( $model );

		$mode     = WGF_Settings::is_test_mode() ? 'test' : 'canli';
		$belge_no = self::fetch_belge_no( $uuid );

		$invoice_id = WGF_Invoice_Repository::create( [
			'order_id'        => $order_id,
			'uuid'            => $uuid,
			'belge_no'        => $belge_no,
			'durum'           => WGF_Invoice_Repository::STATUS_DRAFT,
			'fatura_tipi'     => $model->aliciUnvan ? 'kurumsal' : 'bireysel',
			'mode'            => $mode,
			'para_birimi'     => $model->paraBirimi->name,
			'tutar'           => $model->getPaymentTotal(),
			'alici_ad'        => trim( $model->aliciUnvan ?: ( $model->aliciAdi . ' ' . $model->aliciSoyadi ) ),
			'alici_vkn_tckn'  => $model->vknTckn,
			'irsaliye_no'     => $model->irsaliyeNumarasi ?: null,
			'irsaliye_tarihi' => $model->irsaliyeTarihi ?: null,
		] );

		$order->update_meta_data( '_wgf_invoice_id', $invoice_id );
		$order->save();

		$order->add_order_note( sprintf(
			/* translators: 1: mod (Test/Canlı) 2: UUID */
			__( 'GİB e-Fatura taslağı oluşturuldu (%1$s). UUID: %2$s', 'woo-gib-efatura' ),
			'test' === $mode ? __( 'Test', 'woo-gib-efatura' ) : __( 'Canlı', 'woo-gib-efatura' ),
			$uuid
		) );

		WGF_Logger::info( 'Fatura taslağı oluşturuldu', [ 'order_id' => $order_id, 'uuid' => $uuid, 'mode' => $mode ] );

		return WGF_Invoice_Repository::find( $invoice_id );
	}

	/**
	 * SMS doğrulamasını başlatır (yalnızca canlı modda kullanılabilir).
	 *
	 * @throws WGF_Exception
	 */
	public static function start_signing( int $invoice_id ): array {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_DRAFT !== $row['durum'] ) {
			throw new WGF_Exception( __( 'Yalnızca taslak durumundaki faturalar imzalanabilir.', 'woo-gib-efatura' ) );
		}
		if ( 'test' === $row['mode'] ) {
			throw new WGF_Exception( __( 'Test hesaplarında SMS ile imzalama yapılamaz (GİB kısıtlaması). Canlı API\'ye geçtikten sonra imzalayabilirsiniz.', 'woo-gib-efatura' ) );
		}

		$oid = WGF_Gib_Service::start_sms_verification();

		WGF_Invoice_Repository::update( $invoice_id, [ 'sms_oid' => $oid ] );

		return [ 'oid' => $oid ];
	}

	/**
	 * SMS kodunu doğrular, faturayı resmi olarak imzalar, dosyasını indirir ve
	 * ayarlara göre müşteriye e-posta gönderir.
	 *
	 * @throws WGF_Exception
	 */
	public static function complete_signing( int $invoice_id, string $code ): array {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_DRAFT !== $row['durum'] ) {
			throw new WGF_Exception( __( 'Yalnızca taslak durumundaki faturalar imzalanabilir.', 'woo-gib-efatura' ) );
		}
		if ( empty( $row['sms_oid'] ) ) {
			throw new WGF_Exception( __( 'Önce SMS doğrulamasını başlatmalısınız.', 'woo-gib-efatura' ) );
		}

		WGF_Gib_Service::complete_sms_verification( $code, $row['sms_oid'], $row['uuid'] );

		$belge_no = self::fetch_belge_no( $row['uuid'] );

		WGF_Invoice_Repository::update( $invoice_id, [
			'durum'    => WGF_Invoice_Repository::STATUS_SIGNED,
			'belge_no' => $belge_no,
			'sms_oid'  => null,
		] );

		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order ) {
			$order->add_order_note( sprintf(
				/* translators: %s: belge numarası */
				__( 'GİB e-Fatura SMS ile imzalandı ve resmi hâle geldi. Belge No: %s', 'woo-gib-efatura' ),
				$belge_no ?: '-'
			) );
			$order->update_meta_data( '_wgf_invoice_status', WGF_Invoice_Repository::STATUS_SIGNED );
			$order->save();
		}

		WGF_Logger::info( 'Fatura imzalandı', [ 'invoice_id' => $invoice_id, 'belge_no' => $belge_no ] );

		try {
			self::download_and_store( $invoice_id );
		} catch ( WGF_Exception $e ) {
			WGF_Logger::error( 'İmza sonrası dosya indirme başarısız: ' . $e->getMessage(), [ 'invoice_id' => $invoice_id ] );
		}

		if ( WGF_Settings::get( 'auto_email' ) ) {
			try {
				self::send_email( $invoice_id );
			} catch ( WGF_Exception $e ) {
				WGF_Logger::error( 'Otomatik e-posta gönderilemedi: ' . $e->getMessage(), [ 'invoice_id' => $invoice_id ] );
			}
		}

		return WGF_Invoice_Repository::find( $invoice_id );
	}

	private static function fetch_belge_no( string $uuid ): ?string {
		try {
			$doc = WGF_Gib_Service::get_document( $uuid );
			return $doc['belgeNumarasi'] ?? null;
		} catch ( WGF_Exception $e ) {
			return null;
		}
	}

	/**
	 * Daha önce irsaliyesiz oluşturulmuş bir TASLAK faturaya irsaliye bilgisi ekler.
	 * Aynı belgeyi (uuid + belge numarasıyla) GİB'e güncelleme isteği olarak gönderir;
	 * yeni bir belge oluşturmaz. İmzalanmış faturalar mevzuat gereği değiştirilemediği
	 * için bu işlem yalnızca taslak durumundaki faturalarda kullanılabilir.
	 *
	 * @throws WGF_Exception
	 */
	public static function add_irsaliye( int $invoice_id, string $irsaliye_no, string $irsaliye_tarihi ): array {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_DRAFT !== $row['durum'] ) {
			throw new WGF_Exception( __( 'İrsaliye bilgisi yalnızca taslak durumundaki faturalara eklenebilir. İmzalanmış faturalar mevzuat gereği değiştirilemez.', 'woo-gib-efatura' ) );
		}
		if ( ! empty( $row['irsaliye_no'] ) ) {
			throw new WGF_Exception( __( 'Bu faturada zaten bir irsaliye bilgisi kayıtlı.', 'woo-gib-efatura' ) );
		}
		if ( '' === trim( $irsaliye_no ) ) {
			throw new WGF_Exception( __( 'İrsaliye numarası boş olamaz.', 'woo-gib-efatura' ) );
		}

		$belge_no = $row['belge_no'] ?: self::fetch_belge_no( $row['uuid'] );
		if ( ! $belge_no ) {
			throw new WGF_Exception( __( 'Bu faturanın GİB belge numarası tespit edilemediği için güvenli bir şekilde güncellenemiyor. Gerekirse taslağı silip irsaliye bilgisiyle yeniden oluşturun.', 'woo-gib-efatura' ) );
		}

		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order ) {
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'woo-gib-efatura' ) );
		}

		try {
			$model = WGF_Order_Builder::build( $order, [
				'irsaliyeNumarasi' => $irsaliye_no,
				'irsaliyeTarihi'   => $irsaliye_tarihi,
			] );
		} catch ( FaturaException $e ) {
			throw new WGF_Exception( $e->getMessage() );
		}

		// uuid + belgeNumarasi birlikte set edildiğinde createDraft() yeni belge açmaz,
		// mevcut taslağı GİB üzerinde günceller (bkz. Gib::createDraft() / $isNewDraft kontrolü).
		$model->setUuid( $row['uuid'] );
		$model->belgeNumarasi = $belge_no;

		WGF_Gib_Service::create_draft( $model );

		WGF_Invoice_Repository::update( $invoice_id, [
			'belge_no'        => $belge_no,
			'irsaliye_no'     => $irsaliye_no,
			'irsaliye_tarihi' => $irsaliye_tarihi ?: null,
		] );

		$order->add_order_note( sprintf(
			/* translators: %s: irsaliye numarası */
			__( 'GİB e-Fatura taslağına irsaliye bilgisi eklendi: %s', 'woo-gib-efatura' ),
			$irsaliye_no
		) );
		$order->save();

		WGF_Logger::info( 'İrsaliye eklendi', [ 'invoice_id' => $invoice_id, 'irsaliye_no' => $irsaliye_no ] );

		return WGF_Invoice_Repository::find( $invoice_id );
	}

	/**
	 * İmzalanmış faturanın dosyasını GİB portalından indirip site üzerinde saklar.
	 *
	 * @throws WGF_Exception
	 */
	public static function download_and_store( int $invoice_id ): string {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_SIGNED !== $row['durum'] ) {
			throw new WGF_Exception( __( 'Yalnızca imzalanmış faturalar indirilebilir.', 'woo-gib-efatura' ) );
		}

		$dir      = WGF_Install::upload_dir();
		$filename = 'siparis-' . $row['order_id'] . '-' . substr( $row['uuid'], 0, 8 );

		$full_path = WGF_Gib_Service::save_to_disk( $row['uuid'], $dir, $filename );

		WGF_Invoice_Repository::update( $invoice_id, [ 'dosya_yolu' => $full_path ] );

		return $full_path;
	}

	/**
	 * Yalnızca taslak (henüz imzalanmamış) fatura silinebilir.
	 *
	 * @throws WGF_Exception
	 */
	public static function delete_draft( int $invoice_id ): void {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_DRAFT !== $row['durum'] ) {
			throw new WGF_Exception( __( 'Yalnızca taslak durumundaki faturalar silinebilir. İmzalanmış (resmi) faturalar mevzuat gereği silinemez, yalnızca iptal/itiraz talebi oluşturulabilir.', 'woo-gib-efatura' ) );
		}

		WGF_Gib_Service::delete_draft( $row['uuid'] );

		WGF_Invoice_Repository::update( $invoice_id, [ 'durum' => WGF_Invoice_Repository::STATUS_DELETED ] );

		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order ) {
			$order->delete_meta_data( '_wgf_invoice_id' );
			$order->delete_meta_data( '_wgf_invoice_status' );
			$order->add_order_note( __( 'GİB e-Fatura taslağı silindi.', 'woo-gib-efatura' ) );
			$order->save();
		}
	}

	/**
	 * Faturayı müşteriye e-posta ile gönderir.
	 *
	 * @throws WGF_Exception
	 */
	public static function send_email( int $invoice_id ): void {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_SIGNED !== $row['durum'] ) {
			throw new WGF_Exception( __( 'Yalnızca imzalanmış faturalar e-posta ile gönderilebilir.', 'woo-gib-efatura' ) );
		}

		if ( empty( $row['dosya_yolu'] ) || ! file_exists( $row['dosya_yolu'] ) ) {
			self::download_and_store( $invoice_id );
			$row = self::require_row( $invoice_id );
		}

		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order ) {
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'woo-gib-efatura' ) );
		}

		// WC_Emails::instance() WooCommerce'in WC_Email temel sınıfını (ve ilgili tüm e-posta
		// altyapısını) henüz yüklenmediyse yükler; WGF_Email bu sınıfı extend ettiği için
		// class-wgf-email.php dosyası ancak bundan sonra require edilebilir.
		\WC_Emails::instance();
		require_once WGF_PATH . 'includes/class-wgf-email.php';

		$email = new WGF_Email();
		$email->trigger( $order, $row );

		WGF_Logger::info( 'Fatura e-postası gönderildi', [ 'invoice_id' => $invoice_id, 'order_id' => $row['order_id'] ] );
	}

	/**
	 * @throws WGF_Exception
	 */
	private static function require_row( int $invoice_id ): array {
		$row = WGF_Invoice_Repository::find( $invoice_id );
		if ( ! $row ) {
			throw new WGF_Exception( __( 'Fatura kaydı bulunamadı.', 'woo-gib-efatura' ) );
		}
		return $row;
	}
}
