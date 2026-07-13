<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin tarafı AJAX uç noktaları. Tüm işlemler nonce + manage_woocommerce yetkisi ister.
 */
class WGF_Ajax {

	public static function register_hooks(): void {
		$actions = [
			'wgf_create_invoice',
			'wgf_start_sms',
			'wgf_complete_sms',
			'wgf_delete_draft',
			'wgf_add_irsaliye',
			'wgf_send_email',
			'wgf_fetch_test_credentials',
			'wgf_reset_plugin',
			'wgf_create_return_invoice',
			'wgf_cancellation_request',
		];

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, $action ] );
		}
	}

	private static function check_permission(): void {
		check_ajax_referer( 'wgf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Bu işlem için yetkiniz yok.', 'gib-efatura-for-woocommerce' ) ], 403 );
		}
	}

	public static function wgf_create_invoice(): void {
		self::check_permission();

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( [ 'message' => __( 'Geçersiz sipariş.', 'gib-efatura-for-woocommerce' ) ] );
		}

		$allowed = [ 'aliciAdi', 'aliciSoyadi', 'aliciUnvan', 'vknTckn', 'vergiDairesi', 'adres', 'mahalleSemtIlce', 'sehir', 'ulke', 'postaKodu', 'tel', 'eposta', 'not', 'dovizKuru', 'irsaliyeNumarasi', 'irsaliyeTarihi', 'faturaTarihi' ];
		$overrides = [];
		foreach ( $allowed as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( 'dovizKuru' === $key ) {
					$overrides[ $key ] = (float) wp_unslash( $_POST[ $key ] );
				} elseif ( 'not' === $key ) {
					// sanitize_text_field() satır sonlarını kaldırır; açıklama/not alanı çok satırlı olabildiği
					// için burada satır sonlarını koruyan sanitize_textarea_field() kullanılmalı.
					$overrides[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
				} else {
					$overrides[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				}
			}
		}

		try {
			$invoice = WGF_Invoice_Service::create_invoice( $order_id, $overrides );
			wp_send_json_success( [
				'message' => __( 'Fatura taslağı başarıyla oluşturuldu.', 'gib-efatura-for-woocommerce' ),
				'invoice' => $invoice,
			] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_start_sms(): void {
		self::check_permission();
		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );

		try {
			$result = WGF_Invoice_Service::start_signing( $invoice_id );
			wp_send_json_success( [
				'message' => __( 'SMS kodu telefonunuza gönderildi.', 'gib-efatura-for-woocommerce' ),
				'data'    => $result,
			] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_complete_sms(): void {
		self::check_permission();
		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );
		$code       = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );

		if ( '' === $code ) {
			wp_send_json_error( [ 'message' => __( 'SMS kodu boş olamaz.', 'gib-efatura-for-woocommerce' ) ] );
		}

		try {
			$invoice = WGF_Invoice_Service::complete_signing( $invoice_id, $code );
			wp_send_json_success( [
				'message' => __( 'Fatura başarıyla imzalandı.', 'gib-efatura-for-woocommerce' ),
				'invoice' => $invoice,
			] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_delete_draft(): void {
		self::check_permission();
		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );

		try {
			WGF_Invoice_Service::delete_draft( $invoice_id );
			wp_send_json_success( [ 'message' => __( 'Taslak silindi.', 'gib-efatura-for-woocommerce' ) ] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_add_irsaliye(): void {
		self::check_permission();
		$invoice_id      = absint( $_POST['invoice_id'] ?? 0 );
		$irsaliye_no     = sanitize_text_field( wp_unslash( $_POST['irsaliyeNumarasi'] ?? '' ) );
		$irsaliye_tarihi = sanitize_text_field( wp_unslash( $_POST['irsaliyeTarihi'] ?? '' ) );

		try {
			$invoice = WGF_Invoice_Service::add_irsaliye( $invoice_id, $irsaliye_no, $irsaliye_tarihi );
			wp_send_json_success( [
				'message' => __( 'İrsaliye bilgisi faturaya eklendi.', 'gib-efatura-for-woocommerce' ),
				'invoice' => $invoice,
			] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_send_email(): void {
		self::check_permission();
		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );

		try {
			WGF_Invoice_Service::send_email( $invoice_id );
			wp_send_json_success( [ 'message' => __( 'Fatura müşteriye e-posta ile gönderildi.', 'gib-efatura-for-woocommerce' ) ] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_create_return_invoice(): void {
		self::check_permission();

		$invoice_id = absint( $_POST['invoice_id'] ?? 0 );
		if ( ! $invoice_id ) {
			wp_send_json_error( [ 'message' => __( 'Geçersiz fatura.', 'gib-efatura-for-woocommerce' ) ] );
		}

		$kalemler = [];
		if ( isset( $_POST['kalemler'] ) && is_array( $_POST['kalemler'] ) ) {
			foreach ( wp_unslash( $_POST['kalemler'] ) as $item_id => $qty ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				$qty = (float) $qty;
				if ( $qty > 0 ) {
					$kalemler[ absint( $item_id ) ] = $qty;
				}
			}
		}

		$overrides = array_filter( [
			'faturaTarihi' => isset( $_POST['faturaTarihi'] ) ? sanitize_text_field( wp_unslash( $_POST['faturaTarihi'] ) ) : '',
			'not'          => isset( $_POST['not'] ) ? sanitize_textarea_field( wp_unslash( $_POST['not'] ) ) : '',
		] );

		try {
			$invoice = WGF_Invoice_Service::create_return_invoice( $invoice_id, $kalemler, $overrides );
			wp_send_json_success( [
				'message' => __( 'İade faturası taslağı başarıyla oluşturuldu.', 'gib-efatura-for-woocommerce' ),
				'invoice' => $invoice,
			] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_cancellation_request(): void {
		self::check_permission();

		$invoice_id  = absint( $_POST['invoice_id'] ?? 0 );
		$explanation = sanitize_textarea_field( wp_unslash( $_POST['explanation'] ?? '' ) );

		try {
			$invoice = WGF_Invoice_Service::request_cancellation( $invoice_id, $explanation );
			wp_send_json_success( [
				'message' => __( 'İptal başvurusu GİB portalına iletildi.', 'gib-efatura-for-woocommerce' ),
				'invoice' => $invoice,
			] );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_fetch_test_credentials(): void {
		self::check_permission();

		try {
			$credentials = WGF_Gib_Service::fetch_test_credentials();
			wp_send_json_success( $credentials );
		} catch ( WGF_Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function wgf_reset_plugin(): void {
		self::check_permission();
		$delete_files = ! empty( $_POST['delete_files'] );

		WGF_Install::reset_plugin( $delete_files );

		wp_send_json_success( [ 'message' => __( 'Eklenti sıfırlandı.', 'gib-efatura-for-woocommerce' ) ] );
	}
}
