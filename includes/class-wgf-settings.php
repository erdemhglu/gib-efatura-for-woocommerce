<?php
defined( 'ABSPATH' ) || exit;

/**
 * Eklenti ayarları: API bağlantısı, fatura varsayılanları, alan eşleştirme, sıfırlama.
 * Tüm ayarlar tek bir "wgf_settings" seçeneği altında dizi olarak tutulur.
 */
class WGF_Settings {

	public const OPTION_KEY = 'wgf_settings';
	public const CAPABILITY = 'manage_woocommerce';

	public static function default_options(): array {
		return [
			'test_mode'                => true,
			'prod_username'            => '',
			'prod_password'            => '',
			'test_username'            => '',
			'test_password'            => '',
			'default_note'             => "Siparişiniz için teşekkür ederiz. Sipariş No: {siparis_no}",
			'default_country'          => 'Türkiye',
			'default_district'         => 'Merkez',
			'default_tax_id'           => '11111111111',
			'default_unit'             => 'Adet',
			'field_map_tax_id'         => '_billing_tckn,_billing_vkn,_billing_tax_number,_billing_tc_no,billing_tckn,billing_vkn',
			'field_map_tax_office'     => '_billing_tax_office,_billing_vergi_dairesi,billing_tax_office',
			'field_map_district'       => '_billing_district,_billing_ilce,billing_district,billing_neighborhood',
			'field_map_city'           => '_billing_city_name,_billing_il,billing_city_name,billing_il',
			'swap_address_lines'       => true,
			'convert_plate_code_to_city' => true,
			'vat_included_item_patterns' => '',
			'vat_included_rate'        => 20,
			'auto_email'               => true,
			'customer_download'        => true,
			'delete_data_on_uninstall' => false,
		];
	}

	public static function all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return array_merge( self::default_options(), $saved );
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	public static function update( array $values ): void {
		update_option( self::OPTION_KEY, array_merge( self::all(), $values ) );
	}

	public static function is_test_mode(): bool {
		return (bool) self::get( 'test_mode', true );
	}

	public static function username(): string {
		return self::is_test_mode() ? (string) self::get( 'test_username', '' ) : (string) self::get( 'prod_username', '' );
	}

	public static function password(): string {
		$encrypted = self::is_test_mode() ? (string) self::get( 'test_password', '' ) : (string) self::get( 'prod_password', '' );
		return WGF_Crypto::decrypt( $encrypted );
	}

	/**
	 * Nokta ile ayrılmış alan eşleştirme anahtarlarını diziye çevirir.
	 */
	public static function field_map( string $key ): array {
		$raw = (string) self::get( $key, '' );
		$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		return array_values( $parts );
	}

	public static function register_hooks(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_post_wgf_save_settings', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	/** @var string[] Bu sınıfın kaydettiği admin sayfalarına ait gerçek hook suffix'leri. */
	private static array $page_hooks = [];

	public static function register_menu(): void {
		self::$page_hooks[] = add_menu_page(
			__( 'GİB e-Fatura', 'gib-efatura-for-woocommerce' ),
			__( 'GİB e-Fatura', 'gib-efatura-for-woocommerce' ),
			self::CAPABILITY,
			'wgf-invoices',
			[ 'WGF_Admin_List', 'render' ],
			'dashicons-media-spreadsheet',
			56
		);

		self::$page_hooks[] = add_submenu_page(
			'wgf-invoices',
			__( 'Faturalar', 'gib-efatura-for-woocommerce' ),
			__( 'Faturalar', 'gib-efatura-for-woocommerce' ),
			self::CAPABILITY,
			'wgf-invoices',
			[ 'WGF_Admin_List', 'render' ]
		);

		self::$page_hooks[] = add_submenu_page(
			'wgf-invoices',
			__( 'GİB e-Fatura Ayarları', 'gib-efatura-for-woocommerce' ),
			__( 'Ayarlar', 'gib-efatura-for-woocommerce' ),
			self::CAPABILITY,
			'wgf-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, self::$page_hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'wgf-admin', WGF_URL . 'assets/css/admin.css', [], WGF_VERSION );
		wp_enqueue_script( 'wgf-admin', WGF_URL . 'assets/js/admin.js', [ 'jquery' ], WGF_VERSION, true );
		wp_localize_script( 'wgf-admin', 'WGF', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wgf_nonce' ),
			'i18n'    => [
				'confirmReset'         => __( 'Eklenti tüm ayarları ve fatura kayıtlarını sıfırlayacak. Bu işlem geri alınamaz. Devam edilsin mi?', 'gib-efatura-for-woocommerce' ),
				'confirmResetFiles'    => __( 'Kayıtlı fatura dosyaları da silinsin mi? "Tamam" = dosyalar da silinsin, "İptal" = sadece kayıtlar sıfırlansın.', 'gib-efatura-for-woocommerce' ),
				'genericError'         => __( 'Bir hata oluştu, lütfen tekrar deneyin.', 'gib-efatura-for-woocommerce' ),
				'enterSmsCode'         => __( 'Telefonunuza gelen SMS kodunu girin:', 'gib-efatura-for-woocommerce' ),
				'bulkSignNoneSelected' => __( 'Lütfen imzalamak için en az bir taslak fatura seçin.', 'gib-efatura-for-woocommerce' ),
				/* translators: %d bir JS placeholder'ıdır, sayıya çevrilecektir */
				'bulkSignSelectedCount' => __( '%d taslak fatura seçildi.', 'gib-efatura-for-woocommerce' ),
				'bulkPurgeNoneSelected' => __( 'Lütfen kalıcı olarak silmek için en az bir "silindi" durumundaki kayıt seçin.', 'gib-efatura-for-woocommerce' ),
				'confirmBulkPurge'      => __( 'Seçilen kayıtlar veritabanından kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?', 'gib-efatura-for-woocommerce' ),
				'confirmPurge'          => __( 'Bu fatura kaydı veritabanından kalıcı olarak silinecek. Bu işlem geri alınamaz. Devam edilsin mi?', 'gib-efatura-for-woocommerce' ),
			],
		] );
	}

	public static function handle_save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'gib-efatura-for-woocommerce' ) );
		}
		check_admin_referer( 'wgf_save_settings' );

		$current = self::all();

		$values = [
			'test_mode'                => ! empty( $_POST['test_mode'] ),
			'prod_username'            => sanitize_text_field( wp_unslash( $_POST['prod_username'] ?? '' ) ),
			'test_username'            => sanitize_text_field( wp_unslash( $_POST['test_username'] ?? '' ) ),
			'default_note'             => sanitize_textarea_field( wp_unslash( $_POST['default_note'] ?? '' ) ),
			'default_country'          => sanitize_text_field( wp_unslash( $_POST['default_country'] ?? '' ) ),
			'default_district'         => sanitize_text_field( wp_unslash( $_POST['default_district'] ?? '' ) ),
			'default_tax_id'           => preg_replace( '/\D/', '', wp_unslash( $_POST['default_tax_id'] ?? '' ) ),
			'default_unit'             => sanitize_text_field( wp_unslash( $_POST['default_unit'] ?? '' ) ),
			'field_map_tax_id'         => sanitize_text_field( wp_unslash( $_POST['field_map_tax_id'] ?? '' ) ),
			'field_map_tax_office'     => sanitize_text_field( wp_unslash( $_POST['field_map_tax_office'] ?? '' ) ),
			'field_map_district'       => sanitize_text_field( wp_unslash( $_POST['field_map_district'] ?? '' ) ),
			'field_map_city'           => sanitize_text_field( wp_unslash( $_POST['field_map_city'] ?? '' ) ),
			'swap_address_lines'       => ! empty( $_POST['swap_address_lines'] ),
			'convert_plate_code_to_city' => ! empty( $_POST['convert_plate_code_to_city'] ),
			'vat_included_item_patterns' => sanitize_textarea_field( wp_unslash( $_POST['vat_included_item_patterns'] ?? '' ) ),
			'vat_included_rate'        => max( 0, (float) ( $_POST['vat_included_rate'] ?? 20 ) ),
			'auto_email'               => ! empty( $_POST['auto_email'] ),
			'customer_download'        => ! empty( $_POST['customer_download'] ),
			'delete_data_on_uninstall' => ! empty( $_POST['delete_data_on_uninstall'] ),
		];

		// Parolalar yalnızca yeni bir değer girildiyse güncellenir (boş bırakılırsa mevcut değer korunur).
		$prod_password = (string) wp_unslash( $_POST['prod_password'] ?? '' );
		if ( '' !== $prod_password ) {
			$values['prod_password'] = WGF_Crypto::encrypt( $prod_password );
		} else {
			$values['prod_password'] = $current['prod_password'];
		}

		$test_password = (string) wp_unslash( $_POST['test_password'] ?? '' );
		if ( '' !== $test_password ) {
			$values['test_password'] = WGF_Crypto::encrypt( $test_password );
		} else {
			$values['test_password'] = $current['test_password'];
		}

		self::update( $values );

		wp_safe_redirect( add_query_arg( [ 'page' => 'wgf-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$s = self::all();
		include WGF_PATH . 'includes/views/settings-page.php';
	}
}
