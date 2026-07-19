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
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		$existing = WGF_Invoice_Repository::find_active_by_order( $order_id );
		if ( $existing ) {
			throw new WGF_Exception(
				sprintf(
					/* translators: %s: mevcut fatura belge no ya da durumu */
					__( 'Bu sipariş için zaten bir GİB faturası oluşturulmuş (%s). Mükerrer fatura oluşturulamaz. Gerekiyorsa önce mevcut taslağı silin.', 'gib-efatura-for-woocommerce' ),
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
			'belge_turu'      => 'satis',
			'mode'            => $mode,
			'para_birimi'     => $model->paraBirimi->name,
			'tutar'           => $model->getPaymentTotal(),
			'alici_ad'        => trim( $model->aliciUnvan ?: ( $model->aliciAdi . ' ' . $model->aliciSoyadi ) ),
			'alici_vkn_tckn'  => $model->vknTckn,
			'irsaliye_no'     => $model->irsaliyeNumarasi ?: null,
			'irsaliye_tarihi' => $model->irsaliyeTarihi ?: null,
			'fatura_tarihi'   => $model->tarih,
		] );

		$order->update_meta_data( '_wgf_invoice_id', $invoice_id );
		$order->save();

		$order->add_order_note( sprintf(
			/* translators: 1: mod (Test/Canlı) 2: UUID */
			__( 'GİB e-Fatura taslağı oluşturuldu (%1$s). UUID: %2$s', 'gib-efatura-for-woocommerce' ),
			'test' === $mode ? __( 'Test', 'gib-efatura-for-woocommerce' ) : __( 'Canlı', 'gib-efatura-for-woocommerce' ),
			$uuid
		) );

		WGF_Logger::info( 'Fatura taslağı oluşturuldu', [ 'order_id' => $order_id, 'uuid' => $uuid, 'mode' => $mode ] );

		return WGF_Invoice_Repository::find( $invoice_id );
	}

	/**
	 * İmzalanmış bir satış faturasına karşı iade faturası taslağı oluşturur.
	 * Orijinal faturanın belge no/tarihi otomatik referans alınır; yalnızca seçilen
	 * sipariş kalemleri (tam veya kısmi miktarla) iade faturasına dahil edilir.
	 *
	 * @param array<int,float> $item_quantities Sipariş kalemi ID => iade edilecek miktar.
	 *
	 * @throws WGF_Exception
	 */
	public static function create_return_invoice( int $source_invoice_id, array $item_quantities, array $overrides = [] ): array {
		$source = self::require_row( $source_invoice_id );

		if ( 'satis' !== ( $source['belge_turu'] ?? 'satis' ) ) {
			throw new WGF_Exception( __( 'Yalnızca bir satış faturasına karşı iade faturası oluşturulabilir.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( WGF_Invoice_Repository::STATUS_SIGNED !== $source['durum'] ) {
			throw new WGF_Exception( __( 'İade faturası yalnızca imzalanmış (resmi) satış faturaları için oluşturulabilir.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( empty( $source['belge_no'] ) || empty( $source['fatura_tarihi'] ) ) {
			throw new WGF_Exception( __( 'Orijinal faturanın belge numarası/tarihi tespit edilemediği için iade faturası oluşturulamıyor.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( ! array_filter( $item_quantities, fn( $qty ) => $qty > 0 ) ) {
			throw new WGF_Exception( __( 'İade edilecek en az bir kalem seçmelisiniz.', 'gib-efatura-for-woocommerce' ) );
		}

		$order = wc_get_order( (int) $source['order_id'] );
		if ( ! $order ) {
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		try {
			$model = WGF_Order_Builder::build( $order, $overrides, [
				'faturaNo' => (string) $source['belge_no'],
				'tarihi'   => (string) $source['fatura_tarihi'],
				'kalemler' => $item_quantities,
			] );
		} catch ( FaturaException $e ) {
			throw new WGF_Exception( $e->getMessage() );
		}

		$uuid = WGF_Gib_Service::create_draft( $model );

		$mode     = WGF_Settings::is_test_mode() ? 'test' : 'canli';
		$belge_no = self::fetch_belge_no( $uuid );

		$invoice_id = WGF_Invoice_Repository::create( [
			'order_id'       => (int) $source['order_id'],
			'uuid'           => $uuid,
			'belge_no'       => $belge_no,
			'durum'          => WGF_Invoice_Repository::STATUS_DRAFT,
			'fatura_tipi'    => $source['fatura_tipi'],
			'belge_turu'     => 'iade',
			'iade_kaynak_id' => $source_invoice_id,
			'mode'           => $mode,
			'para_birimi'    => $model->paraBirimi->name,
			'tutar'          => $model->getPaymentTotal(),
			'alici_ad'       => trim( $model->aliciUnvan ?: ( $model->aliciAdi . ' ' . $model->aliciSoyadi ) ),
			'alici_vkn_tckn' => $model->vknTckn,
			'fatura_tarihi'  => $model->tarih,
		] );

		$order->add_order_note( sprintf(
			/* translators: 1: mod (Test/Canlı) 2: iade edilen belge no 3: UUID */
			__( 'GİB e-Fatura iade taslağı oluşturuldu (%1$s). Referans belge: %2$s. UUID: %3$s', 'gib-efatura-for-woocommerce' ),
			'test' === $mode ? __( 'Test', 'gib-efatura-for-woocommerce' ) : __( 'Canlı', 'gib-efatura-for-woocommerce' ),
			$source['belge_no'],
			$uuid
		) );

		WGF_Logger::info( 'İade faturası taslağı oluşturuldu', [ 'order_id' => $source['order_id'], 'kaynak_invoice_id' => $source_invoice_id, 'uuid' => $uuid, 'mode' => $mode ] );

		return WGF_Invoice_Repository::find( $invoice_id );
	}

	/**
	 * İmzalanmış (resmi) bir fatura için GİB portalına iptal başvurusu gönderir.
	 * Alıcı yasal süre içinde itiraz etmezse başvuru kendiliğinden onaylanır; bu yüzden
	 * bu işlem faturanın durumunu hemen "iptal" yapmaz, yalnızca başvuruyu kaydeder.
	 *
	 * @throws WGF_Exception
	 */
	public static function request_cancellation( int $invoice_id, string $explanation ): array {
		$row = self::require_row( $invoice_id );

		if ( WGF_Invoice_Repository::STATUS_SIGNED !== $row['durum'] ) {
			throw new WGF_Exception( __( 'İptal başvurusu yalnızca imzalanmış (resmi) faturalar için yapılabilir.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( ! empty( $row['iptal_talep_tarihi'] ) ) {
			throw new WGF_Exception( __( 'Bu fatura için zaten bir iptal başvurusu gönderilmiş.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( '' === trim( $explanation ) ) {
			throw new WGF_Exception( __( 'İptal başvurusu için bir açıklama girmelisiniz.', 'gib-efatura-for-woocommerce' ) );
		}

		WGF_Gib_Service::cancellation_request( $row['uuid'], $explanation );

		WGF_Invoice_Repository::mark_cancellation_requested( $invoice_id, $explanation );

		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order ) {
			$order->add_order_note( sprintf(
				/* translators: 1: belge no 2: açıklama */
				__( 'GİB e-Fatura için iptal başvurusu gönderildi. Belge No: %1$s. Açıklama: %2$s', 'gib-efatura-for-woocommerce' ),
				$row['belge_no'] ?: '-',
				$explanation
			) );
			$order->save();
		}

		WGF_Logger::info( 'İptal başvurusu gönderildi', [ 'invoice_id' => $invoice_id, 'uuid' => $row['uuid'] ] );

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
			throw new WGF_Exception( __( 'Yalnızca taslak durumundaki faturalar imzalanabilir.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( 'test' === $row['mode'] ) {
			throw new WGF_Exception( __( 'Test hesaplarında SMS ile imzalama yapılamaz (GİB kısıtlaması). Canlı API\'ye geçtikten sonra imzalayabilirsiniz.', 'gib-efatura-for-woocommerce' ) );
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
			throw new WGF_Exception( __( 'Yalnızca taslak durumundaki faturalar imzalanabilir.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( empty( $row['sms_oid'] ) ) {
			throw new WGF_Exception( __( 'Önce SMS doğrulamasını başlatmalısınız.', 'gib-efatura-for-woocommerce' ) );
		}

		WGF_Gib_Service::complete_sms_verification( $code, $row['sms_oid'], [ $row['uuid'] ] );

		self::finalize_signed_invoice( $invoice_id, $row );

		return WGF_Invoice_Repository::find( $invoice_id );
	}

	/**
	 * Birden fazla taslak fatura için tek bir SMS doğrulaması başlatır (yalnızca canlı modda).
	 * GİB tek bir OID/kodla birden fazla belgeyi imzalayabildiği için, seçilen tüm taslaklara
	 * aynı OID kaydedilir; onay adımında bu ortak OID kullanılır.
	 *
	 * @param int[] $invoice_ids
	 *
	 * @return array{oid:string,count:int}
	 * @throws WGF_Exception
	 */
	public static function start_bulk_signing( array $invoice_ids ): array {
		$rows = self::require_draft_rows_for_signing( $invoice_ids );

		$oid = WGF_Gib_Service::start_sms_verification();

		foreach ( $rows as $row ) {
			WGF_Invoice_Repository::update( (int) $row['id'], [ 'sms_oid' => $oid ] );
		}

		return [ 'oid' => $oid, 'count' => count( $rows ) ];
	}

	/**
	 * Tek bir SMS koduyla, start_bulk_signing() ile OID'i başlatılmış birden fazla taslağı
	 * tek seferde imzalar; her biri için tekli imzalamayla aynı sonrası adımları
	 * (belge no tespiti, sipariş notu, dosya indirme, otomatik e-posta) uygular.
	 *
	 * @param int[] $invoice_ids
	 *
	 * @return array{signed:int[]}
	 * @throws WGF_Exception
	 */
	public static function complete_bulk_signing( array $invoice_ids, string $code ): array {
		$rows = self::require_draft_rows_for_signing( $invoice_ids );

		$oid = (string) ( $rows[0]['sms_oid'] ?? '' );
		if ( '' === $oid ) {
			throw new WGF_Exception( __( 'Önce SMS doğrulamasını başlatmalısınız.', 'gib-efatura-for-woocommerce' ) );
		}

		$uuids = array_map( static fn( array $row ): string => $row['uuid'], $rows );

		WGF_Gib_Service::complete_sms_verification( $code, $oid, $uuids );

		$signed = [];
		foreach ( $rows as $row ) {
			$invoice_id = (int) $row['id'];
			self::finalize_signed_invoice( $invoice_id, $row );
			$signed[] = $invoice_id;
		}

		return [ 'signed' => $signed ];
	}

	/**
	 * complete_signing() ve complete_bulk_signing() ortak son adımları: durumu imzalandı
	 * yapar, belge no'yu tespit eder, sipariş notunu ekler, dosyayı indirir ve
	 * ayarlara göre müşteriye e-posta gönderir.
	 */
	private static function finalize_signed_invoice( int $invoice_id, array $row ): void {
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
				__( 'GİB e-Fatura SMS ile imzalandı ve resmi hâle geldi. Belge No: %s', 'gib-efatura-for-woocommerce' ),
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
	}

	/**
	 * Toplu imzalama için verilen fatura ID'lerini doğrular: hepsi mevcut, taslak
	 * durumunda ve canlı modda olmalı (test hesaplarında GİB SMS ile imzalamayı
	 * desteklemiyor). Herhangi biri koşulu sağlamıyorsa tüm işlem iptal edilir.
	 *
	 * @param int[] $invoice_ids
	 *
	 * @return array[]
	 * @throws WGF_Exception
	 */
	private static function require_draft_rows_for_signing( array $invoice_ids ): array {
		$invoice_ids = array_values( array_unique( array_filter( array_map( 'intval', $invoice_ids ) ) ) );

		if ( ! $invoice_ids ) {
			throw new WGF_Exception( __( 'İmzalamak için en az bir taslak fatura seçmelisiniz.', 'gib-efatura-for-woocommerce' ) );
		}

		$rows = [];
		foreach ( $invoice_ids as $invoice_id ) {
			$row = self::require_row( $invoice_id );

			if ( WGF_Invoice_Repository::STATUS_DRAFT !== $row['durum'] ) {
				throw new WGF_Exception(
					sprintf(
						/* translators: %d: fatura ID */
						__( 'Yalnızca taslak durumundaki faturalar imzalanabilir (#%d taslak değil).', 'gib-efatura-for-woocommerce' ),
						$invoice_id
					)
				);
			}
			if ( 'test' === $row['mode'] ) {
				throw new WGF_Exception( __( 'Test hesaplarında SMS ile imzalama yapılamaz (GİB kısıtlaması). Canlı API\'ye geçtikten sonra imzalayabilirsiniz.', 'gib-efatura-for-woocommerce' ) );
			}

			$rows[] = $row;
		}

		return $rows;
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
			throw new WGF_Exception( __( 'İrsaliye bilgisi yalnızca taslak durumundaki faturalara eklenebilir. İmzalanmış faturalar mevzuat gereği değiştirilemez.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( ! empty( $row['irsaliye_no'] ) ) {
			throw new WGF_Exception( __( 'Bu faturada zaten bir irsaliye bilgisi kayıtlı.', 'gib-efatura-for-woocommerce' ) );
		}
		if ( '' === trim( $irsaliye_no ) ) {
			throw new WGF_Exception( __( 'İrsaliye numarası boş olamaz.', 'gib-efatura-for-woocommerce' ) );
		}

		$belge_no = $row['belge_no'] ?: self::fetch_belge_no( $row['uuid'] );
		if ( ! $belge_no ) {
			throw new WGF_Exception( __( 'Bu faturanın GİB belge numarası tespit edilemediği için güvenli bir şekilde güncellenemiyor. Gerekirse taslağı silip irsaliye bilgisiyle yeniden oluşturun.', 'gib-efatura-for-woocommerce' ) );
		}

		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order ) {
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'gib-efatura-for-woocommerce' ) );
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
			__( 'GİB e-Fatura taslağına irsaliye bilgisi eklendi: %s', 'gib-efatura-for-woocommerce' ),
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
			throw new WGF_Exception( __( 'Yalnızca imzalanmış faturalar indirilebilir.', 'gib-efatura-for-woocommerce' ) );
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
			throw new WGF_Exception( __( 'Yalnızca taslak durumundaki faturalar silinebilir. İmzalanmış (resmi) faturalar mevzuat gereği silinemez, yalnızca iptal/itiraz talebi oluşturulabilir.', 'gib-efatura-for-woocommerce' ) );
		}

		WGF_Gib_Service::delete_draft( $row['uuid'] );

		WGF_Invoice_Repository::update( $invoice_id, [ 'durum' => WGF_Invoice_Repository::STATUS_DELETED ] );

		$order = wc_get_order( (int) $row['order_id'] );
		if ( $order ) {
			if ( 'satis' === ( $row['belge_turu'] ?? 'satis' ) ) {
				$order->delete_meta_data( '_wgf_invoice_id' );
				$order->delete_meta_data( '_wgf_invoice_status' );
			}
			$order->add_order_note( 'iade' === ( $row['belge_turu'] ?? '' )
				? __( 'GİB e-Fatura iade taslağı silindi.', 'gib-efatura-for-woocommerce' )
				: __( 'GİB e-Fatura taslağı silindi.', 'gib-efatura-for-woocommerce' )
			);
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
			throw new WGF_Exception( __( 'Yalnızca imzalanmış faturalar e-posta ile gönderilebilir.', 'gib-efatura-for-woocommerce' ) );
		}

		if ( empty( $row['dosya_yolu'] ) || ! file_exists( $row['dosya_yolu'] ) ) {
			self::download_and_store( $invoice_id );
			$row = self::require_row( $invoice_id );
		}

		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order ) {
			throw new WGF_Exception( __( 'Sipariş bulunamadı.', 'gib-efatura-for-woocommerce' ) );
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
			throw new WGF_Exception( __( 'Fatura kaydı bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}
		return $row;
	}
}
