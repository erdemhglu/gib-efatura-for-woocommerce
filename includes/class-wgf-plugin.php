<?php
defined( 'ABSPATH' ) || exit;

/**
 * Eklentinin ana giriş noktası: tüm sınıfları yükler ve kancaları bağlar.
 */
final class WGF_Plugin {

	private static ?WGF_Plugin $instance = null;

	public static function instance(): WGF_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes(): void {
		$files = [
			'class-wgf-exception.php',
			'class-wgf-logger.php',
			'class-wgf-crypto.php',
			'class-wgf-install.php',
			'class-wgf-settings.php',
			'class-wgf-gib-service.php',
			'class-wgf-order-builder.php',
			'class-wgf-invoice-repository.php',
			'class-wgf-invoice-service.php',
			'class-wgf-order-ui.php',
			'class-wgf-ajax.php',
			'class-wgf-admin-list.php',
			'class-wgf-download-handler.php',
			'class-wgf-myaccount.php',
		];

		foreach ( $files as $file ) {
			$path = WGF_PATH . 'includes/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	private function init_hooks(): void {
		WGF_Settings::register_hooks();
		WGF_Order_UI::register_hooks();
		WGF_Ajax::register_hooks();
		WGF_Admin_List::register_hooks();
		WGF_Download_Handler::register_hooks();
		WGF_MyAccount::register_hooks();

		add_filter( 'woocommerce_email_classes', [ $this, 'register_email_class' ] );

		add_action( 'plugins_loaded', function () {
			if ( get_option( 'wgf_db_version' ) !== WGF_DB_VERSION ) {
				WGF_Install::create_table();
			}
		}, 30 );
	}

	public function register_email_class( array $email_classes ): array {
		// WC_Email sınıfı, WooCommerce bu filtreyi tetiklemeden hemen önce (WC_Emails::init() içinde)
		// yüklenir; bu yüzden class-wgf-email.php dosyasını burada, filtre çalışırken require ediyoruz.
		require_once WGF_PATH . 'includes/class-wgf-email.php';
		$email_classes['WGF_Email'] = new WGF_Email();
		return $email_classes;
	}
}
