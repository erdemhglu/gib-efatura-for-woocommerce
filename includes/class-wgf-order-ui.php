<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sipariş düzenleme ekranındaki "GİB e-Fatura" kutusu ve sipariş listesindeki durum sütunu.
 */
class WGF_Order_UI {

	public static function register_hooks(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

		// Klasik (post tablosu) sipariş listesi.
		add_filter( 'manage_edit-shop_order_columns', [ __CLASS__, 'add_list_column' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'render_list_column' ], 10, 2 );

		// HPOS sipariş listesi.
		add_filter( 'woocommerce_shop_order_list_table_columns', [ __CLASS__, 'add_list_column' ] );
		add_action( 'woocommerce_shop_order_list_table_custom_column', [ __CLASS__, 'render_list_column_hpos' ], 10, 2 );
	}

	public static function add_meta_box(): void {
		$screen = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'wgf-invoice-box',
			__( 'GİB e-Fatura', 'woo-gib-efatura' ),
			[ __CLASS__, 'render_meta_box' ],
			$screen,
			'side',
			'high'
		);
	}

	public static function render_meta_box( $post_or_order ): void {
		$order = ( $post_or_order instanceof \WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$invoice = WGF_Invoice_Repository::find_active_by_order( $order->get_id() );
		$defaults = $invoice ? [] : WGF_Order_Builder::get_defaults( $order );

		include WGF_PATH . 'includes/views/order-metabox.php';
	}

	public static function enqueue( string $hook ): void {
		global $post;

		$is_order_screen = in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && $post && 'shop_order' === $post->post_type;

		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) ) {
			$is_order_screen = $is_order_screen || $hook === wc_get_page_screen_id( 'shop-order' );
		}

		if ( ! $is_order_screen ) {
			return;
		}

		wp_enqueue_style( 'wgf-admin', WGF_URL . 'assets/css/admin.css', [], WGF_VERSION );
		wp_enqueue_script( 'wgf-admin', WGF_URL . 'assets/js/admin.js', [ 'jquery' ], WGF_VERSION, true );
		wp_localize_script( 'wgf-admin', 'WGF', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wgf_nonce' ),
			'i18n'    => [
				'confirmDelete' => __( 'Taslak fatura GİB portalından silinecek. Devam edilsin mi?', 'woo-gib-efatura' ),
				'enterSmsCode'  => __( 'Telefonunuza gelen SMS kodunu girin:', 'woo-gib-efatura' ),
				'genericError'  => __( 'Bir hata oluştu, lütfen tekrar deneyin.', 'woo-gib-efatura' ),
				'currencyRequired' => __( 'Sipariş TRY dışında bir para biriminde, lütfen döviz kurunu girin.', 'woo-gib-efatura' ),
				'irsaliyeRequired' => __( 'İrsaliye numarası boş olamaz.', 'woo-gib-efatura' ),
			],
		] );
	}

	public static function add_list_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['wgf_invoice'] = __( 'GİB Fatura', 'woo-gib-efatura' );
			}
		}
		if ( ! isset( $new['wgf_invoice'] ) ) {
			$new['wgf_invoice'] = __( 'GİB Fatura', 'woo-gib-efatura' );
		}
		return $new;
	}

	public static function render_list_column( string $column, int $post_id ): void {
		if ( 'wgf_invoice' === $column ) {
			self::render_status_badge( $post_id );
		}
	}

	public static function render_list_column_hpos( string $column, $order ): void {
		if ( 'wgf_invoice' === $column ) {
			self::render_status_badge( $order->get_id() );
		}
	}

	private static function render_status_badge( int $order_id ): void {
		$invoice = WGF_Invoice_Repository::find_active_by_order( $order_id );
		if ( ! $invoice ) {
			echo '<span class="wgf-badge wgf-badge-none">&mdash;</span>';
			return;
		}
		$label = WGF_Invoice_Repository::STATUS_SIGNED === $invoice['durum']
			? __( 'İmzalandı', 'woo-gib-efatura' )
			: __( 'Taslak', 'woo-gib-efatura' );

		printf(
			'<span class="wgf-badge wgf-badge-%1$s">%2$s</span><br /><small>%3$s</small>',
			esc_attr( $invoice['durum'] ),
			esc_html( $label ),
			esc_html( 'test' === $invoice['mode'] ? __( 'Test', 'woo-gib-efatura' ) : __( 'Canlı', 'woo-gib-efatura' ) )
		);
	}
}
