<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aktivasyon / deaktivasyon ve tablo kurulum işlemleri.
 */
class WGF_Install {

	public static function activate(): void {
		self::create_table();
		self::maybe_add_default_options();
		self::protect_upload_dir();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * wgf_invoices tablosunu oluşturur (dbDelta ile, gelecekteki güncellemelerde de güvenle çalışır).
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . WGF_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			uuid VARCHAR(64) NULL,
			belge_no VARCHAR(64) NULL,
			durum VARCHAR(20) NOT NULL DEFAULT 'taslak',
			fatura_tipi VARCHAR(20) NOT NULL DEFAULT 'bireysel',
			belge_turu VARCHAR(10) NOT NULL DEFAULT 'satis',
			iade_kaynak_id BIGINT UNSIGNED NULL,
			mode VARCHAR(10) NOT NULL DEFAULT 'test',
			para_birimi VARCHAR(10) NOT NULL DEFAULT 'TRY',
			tutar DECIMAL(18,2) NOT NULL DEFAULT 0,
			alici_ad VARCHAR(255) NULL,
			alici_vkn_tckn VARCHAR(20) NULL,
			irsaliye_no VARCHAR(64) NULL,
			irsaliye_tarihi VARCHAR(20) NULL,
			fatura_tarihi VARCHAR(20) NULL,
			dosya_yolu VARCHAR(500) NULL,
			sms_oid VARCHAR(100) NULL,
			iptal_talep_tarihi DATETIME NULL,
			iptal_aciklama VARCHAR(255) NULL,
			hata_mesaji TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY durum (durum),
			KEY uuid (uuid),
			KEY iade_kaynak_id (iade_kaynak_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'wgf_db_version', WGF_DB_VERSION );
	}

	public static function maybe_add_default_options(): void {
		if ( false === get_option( 'wgf_settings', false ) ) {
			require_once WGF_PATH . 'includes/class-wgf-settings.php';
			add_option( 'wgf_settings', WGF_Settings::default_options() );
		}
	}

	/**
	 * Fatura dosyalarının saklandığı klasörü doğrudan tarayıcı erişimine karşı korur.
	 */
	public static function protect_upload_dir(): void {
		$dir = self::upload_dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$htaccess_file = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, "Require all denied\nDeny from all\n" );
		}
	}

	/**
	 * Fatura dosyalarının saklandığı fiziksel klasör.
	 */
	public static function upload_dir(): string {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'wgf-faturalar';
	}

	/**
	 * Eklentiyi kurulum öncesi hâline sıfırlar (tabloyu ve ayarları temizler).
	 * Uninstall'dan farklı olarak eklenti aktifken de "Ayarlar" sayfasından tetiklenebilir.
	 *
	 * @param bool $delete_files Saklanan fatura dosyalarını da silsin mi.
	 */
	public static function reset_plugin( bool $delete_files = false ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . WGF_TABLE;
		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'wgf_settings' );
		add_option( 'wgf_settings', WGF_Settings::default_options() );

		// Sipariş üzerindeki fatura işaretlerini temizle.
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wgf_invoice_id','_wgf_invoice_status')"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders_table = $wpdb->prefix . 'wc_orders_meta';
			$wpdb->query(
				"DELETE FROM {$orders_table} WHERE meta_key IN ('_wgf_invoice_id','_wgf_invoice_status')"
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $delete_files ) {
			self::delete_all_files();
		}

		WGF_Logger::info( 'Eklenti sıfırlandı.', [ 'delete_files' => $delete_files ] );
	}

	private static function delete_all_files(): void {
		$dir = self::upload_dir();
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( glob( trailingslashit( $dir ) . '*' ) as $file ) {
			$basename = basename( $file );
			if ( in_array( $basename, [ 'index.php', '.htaccess' ], true ) ) {
				continue;
			}
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}
	}
}
