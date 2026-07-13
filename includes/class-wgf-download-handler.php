<?php
defined( 'ABSPATH' ) || exit;

/**
 * Saklanan fatura dosyalarının (zip) güvenli şekilde indirilmesi.
 * Dosyalar wp-content/uploads/wgf-faturalar/ altında, .htaccess ile doğrudan
 * tarayıcı erişimine kapalı şekilde saklanır; indirme yalnızca bu handler üzerinden yapılır.
 */
class WGF_Download_Handler {

	public static function register_hooks(): void {
		add_action( 'admin_post_wgf_download_invoice', [ __CLASS__, 'handle_admin_download' ] );
		add_action( 'admin_post_wgf_download_invoice_customer', [ __CLASS__, 'handle_customer_download' ] );
		add_action( 'admin_post_nopriv_wgf_download_invoice_customer', [ __CLASS__, 'require_login' ] );
		add_action( 'admin_post_wgf_preview_invoice', [ __CLASS__, 'handle_preview' ] );
	}

	public static function require_login(): void {
		wp_die( esc_html__( 'Faturayı indirmek için giriş yapmalısınız.', 'gib-efatura-for-woocommerce' ) );
	}

	public static function handle_admin_download(): void {
		$invoice_id = absint( $_GET['invoice_id'] ?? 0 );

		if ( ! current_user_can( WGF_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'gib-efatura-for-woocommerce' ) );
		}
		check_admin_referer( 'wgf_download_' . $invoice_id );

		$row = WGF_Invoice_Repository::find( $invoice_id );
		if ( ! $row ) {
			wp_die( esc_html__( 'Fatura kaydı bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		self::stream_file( $row );
	}

	public static function handle_customer_download(): void {
		$invoice_id = absint( $_GET['invoice_id'] ?? 0 );

		if ( ! is_user_logged_in() ) {
			self::require_login();
		}
		check_admin_referer( 'wgf_customer_download_' . $invoice_id );

		if ( ! WGF_Settings::get( 'customer_download' ) ) {
			wp_die( esc_html__( 'Fatura indirme özelliği şu anda kapalı.', 'gib-efatura-for-woocommerce' ) );
		}

		$row = WGF_Invoice_Repository::find( $invoice_id );
		if ( ! $row || WGF_Invoice_Repository::STATUS_SIGNED !== $row['durum'] ) {
			wp_die( esc_html__( 'Fatura bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		$order = wc_get_order( (int) $row['order_id'] );
		if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
			wp_die( esc_html__( 'Bu faturayı görüntüleme yetkiniz yok.', 'gib-efatura-for-woocommerce' ) );
		}

		self::stream_file( $row );
	}

	/**
	 * Faturanın GİB portalındaki HTML görünümünü (taslak ya da imzalanmış) yeni sekmede gösterir.
	 */
	public static function handle_preview(): void {
		$invoice_id = absint( $_GET['invoice_id'] ?? 0 );

		if ( ! current_user_can( WGF_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'gib-efatura-for-woocommerce' ) );
		}
		check_admin_referer( 'wgf_preview_' . $invoice_id );

		$row = WGF_Invoice_Repository::find( $invoice_id );
		if ( ! $row ) {
			wp_die( esc_html__( 'Fatura kaydı bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		try {
			$signed = WGF_Invoice_Repository::STATUS_SIGNED === $row['durum'];
			$html   = WGF_Gib_Service::get_html( $row['uuid'], $signed );
		} catch ( WGF_Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		// GİB'den gelen belge, dış/gömülü script çalışmasını engelleyen ama aynı köken (self)
		// script'lerine (ör. Cloudflare'in e-posta maskesini çözen /cdn-cgi/ script'i) izin veren
		// bir CSP ile gösterilir. Bu, GİB yanıtı manipüle edilse bile dışarıdan/gömülü script
		// enjeksiyonuna (XSS) karşı korurken, CDN/güvenlik eklentilerinin sayfayı bozmasını önler.
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data: https:; script-src 'self'; object-src 'none'; base-uri 'none'; form-action 'none';" );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSP ile script çalıştırma engellenmiş ham GİB belge görünümü.
		exit;
	}

	private static function stream_file( array $row ): void {
		if ( empty( $row['dosya_yolu'] ) ) {
			wp_die( esc_html__( 'Bu faturaya ait indirilebilir bir dosya yok.', 'gib-efatura-for-woocommerce' ) );
		}

		$upload_dir = realpath( WGF_Install::upload_dir() );
		$file_path  = realpath( $row['dosya_yolu'] );

		if ( ! $upload_dir || ! $file_path || 0 !== strpos( $file_path, $upload_dir ) || ! is_file( $file_path ) ) {
			wp_die( esc_html__( 'Fatura dosyası bulunamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );

		readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}
}
