<?php
/**
 * Plugin Name:       GİB e-Fatura for WooCommerce
 * Plugin URI:        https://github.com/erdemhglu/gib-efatura-for-woocommerce
 * Description:       WooCommerce siparişlerinden GİB e-Arşiv portalı üzerinden e-Fatura/e-Arşiv fatura kesme, e-posta ile gönderme, indirme/saklama ve mükerrer fatura engelleme eklentisi. mlevent/fatura kütüphanesini kullanır.
 * Version:           1.2.3
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Erdem Hacisalihoglu
 * Author URI:        https://erdemhacisalihoglu.com
 * Text Domain:       gib-efatura-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 7.0
 * WC tested up to:       9.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------
// Sabitler
// ---------------------------------------------------------------------
define( 'WGF_VERSION', '1.2.3' );
define( 'WGF_FILE', __FILE__ );
define( 'WGF_PATH', plugin_dir_path( __FILE__ ) );
define( 'WGF_URL', plugin_dir_url( __FILE__ ) );
define( 'WGF_BASENAME', plugin_basename( __FILE__ ) );
define( 'WGF_TABLE', 'wgf_invoices' );
define( 'WGF_DB_VERSION', '1.2' );

/**
 * Composer autoloader (mlevent/fatura + bağımlılıkları).
 * "composer install" çalıştırıldıktan sonra vendor/autoload.php oluşur.
 */
function wgf_autoload_ready(): bool {
	return file_exists( WGF_PATH . 'vendor/autoload.php' );
}

if ( wgf_autoload_ready() ) {
	require_once WGF_PATH . 'vendor/autoload.php';
}

/**
 * WooCommerce aktif değilse veya composer bağımlılıkları yüklenmemişse uyarı göster ve dur.
 */
function wgf_requirements_met(): bool {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}
	if ( ! wgf_autoload_ready() || ! class_exists( '\\Mlevent\\Fatura\\Gib' ) ) {
		return false;
	}
	return true;
}

add_action( 'admin_notices', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'WooCommerce GİB e-Fatura eklentisinin çalışması için WooCommerce eklentisinin kurulu ve aktif olması gerekir.', 'gib-efatura-for-woocommerce' ) .
			'</p></div>';
		return;
	}
	if ( ! wgf_autoload_ready() || ! class_exists( '\\Mlevent\\Fatura\\Gib' ) ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'WooCommerce GİB e-Fatura: gerekli PHP kütüphaneleri bulunamadı. Eklenti klasöründe "composer install" komutunu çalıştırın (composer.json içinde mlevent/fatura paketi tanımlıdır).', 'gib-efatura-for-woocommerce' ) .
			'</p></div>';
	}
} );

/**
 * HPOS (High-Performance Order Storage) uyumluluk beyanı.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WGF_FILE, true );
	}
} );

/**
 * Aktivasyon / Deaktivasyon
 */
register_activation_hook( WGF_FILE, function () {
	require_once WGF_PATH . 'includes/class-wgf-install.php';
	WGF_Install::activate();
} );

register_deactivation_hook( WGF_FILE, function () {
	require_once WGF_PATH . 'includes/class-wgf-install.php';
	WGF_Install::deactivate();
} );

/**
 * Eklentiyi başlat.
 */
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'gib-efatura-for-woocommerce', false, dirname( WGF_BASENAME ) . '/languages' );

	if ( ! wgf_requirements_met() ) {
		return;
	}

	require_once WGF_PATH . 'includes/class-wgf-plugin.php';
	WGF_Plugin::instance();
}, 20 );
