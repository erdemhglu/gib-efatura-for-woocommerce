<?php
/**
 * Eklenti WordPress admin panelinden tamamen silindiğinde çalışır.
 * Yalnızca Ayarlar sayfasındaki "Eklenti kaldırıldığında verileri sil" seçeneği
 * işaretliyse fatura kayıtlarını, dosyalarını ve ayarları kaldırır.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$settings = get_option( 'wgf_settings', [] );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

// Ayarlar
delete_option( 'wgf_settings' );
delete_option( 'wgf_db_version' );

// Fatura tablosu
$table = $wpdb->prefix . 'wgf_invoices';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Sipariş üzerindeki fatura işaretleri
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wgf_invoice_id','_wgf_invoice_status')"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
	$orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
	$wpdb->query(
		"DELETE FROM {$orders_meta_table} WHERE meta_key IN ('_wgf_invoice_id','_wgf_invoice_status')"
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// WooCommerce e-posta ayarları
delete_option( 'woocommerce_wgf_invoice_email_settings' );

// Saklanan fatura dosyaları
$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'wgf-faturalar';

if ( is_dir( $dir ) ) {
	foreach ( glob( trailingslashit( $dir ) . '*' ) as $file ) {
		if ( is_file( $file ) ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
