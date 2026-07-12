<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * "GİB e-Fatura > Faturalar" sayfasında oluşturulan tüm faturaları listeler.
 */
class WGF_Admin_List extends \WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'wgf_invoice',
			'plural'   => 'wgf_invoices',
			'ajax'     => false,
		] );
	}

	public static function register_hooks(): void {
		// Menü kaydı WGF_Settings::register_menu() içinde yapılır.
	}

	public static function render(): void {
		if ( ! current_user_can( WGF_Settings::CAPABILITY ) ) {
			return;
		}

		$table = new self();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GİB e-Fatura Kayıtları', 'woo-gib-efatura' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="wgf-invoices" />
				<?php $table->search_box( __( 'Ara', 'woo-gib-efatura' ), 'wgf-search' ); ?>
				<?php $table->views_list(); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Durum filtre linkleri (Tümü / Taslak / İmzalandı / Silindi).
	 */
	public function views_list(): void {
		$current = sanitize_text_field( wp_unslash( $_GET['durum'] ?? '' ) );
		$base    = remove_query_arg( 'durum' );

		$statuses = [
			''                                    => __( 'Tümü', 'woo-gib-efatura' ),
			WGF_Invoice_Repository::STATUS_DRAFT  => __( 'Taslak', 'woo-gib-efatura' ),
			WGF_Invoice_Repository::STATUS_SIGNED => __( 'İmzalandı', 'woo-gib-efatura' ),
			WGF_Invoice_Repository::STATUS_DELETED => __( 'Silindi', 'woo-gib-efatura' ),
		];

		$links = [];
		foreach ( $statuses as $value => $label ) {
			$url    = '' === $value ? $base : add_query_arg( 'durum', $value, $base );
			$class  = ( $current === $value ) ? ' class="current"' : '';
			$links[] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class, esc_html( $label ) );
		}

		echo '<ul class="subsubsub"><li>' . implode( ' | </li><li>', $links ) . '</li></ul>';
	}

	public function get_columns(): array {
		return [
			'order_id'    => __( 'Sipariş', 'woo-gib-efatura' ),
			'alici_ad'    => __( 'Müşteri', 'woo-gib-efatura' ),
			'fatura_tipi' => __( 'Tip', 'woo-gib-efatura' ),
			'belge_no'    => __( 'Belge No', 'woo-gib-efatura' ),
			'tutar'       => __( 'Tutar', 'woo-gib-efatura' ),
			'durum'       => __( 'Durum', 'woo-gib-efatura' ),
			'mode'        => __( 'Ortam', 'woo-gib-efatura' ),
			'created_at'  => __( 'Tarih', 'woo-gib-efatura' ),
			'actions'     => __( 'İşlemler', 'woo-gib-efatura' ),
		];
	}

	public function prepare_items(): void {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$args = [
			'durum'    => sanitize_text_field( wp_unslash( $_GET['durum'] ?? '' ) ),
			'search'   => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'per_page' => $per_page,
			'paged'    => $paged,
		];

		$result = WGF_Invoice_Repository::query( $args );

		$this->items = $result['items'];

		$this->_column_headers = [ $this->get_columns(), [], [] ];

		$this->set_pagination_args( [
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $result['total'] / $per_page ),
		] );
	}

	public function column_order_id( array $item ): string {
		$order = wc_get_order( (int) $item['order_id'] );
		if ( ! $order ) {
			return '#' . esc_html( $item['order_id'] );
		}
		return sprintf(
			'<a href="%s">#%s</a>',
			esc_url( $order->get_edit_order_url() ),
			esc_html( $order->get_order_number() )
		);
	}

	public function column_tutar( array $item ): string {
		return esc_html( number_format_i18n( (float) $item['tutar'], 2 ) . ' ' . $item['para_birimi'] );
	}

	public function column_fatura_tipi( array $item ): string {
		return esc_html( 'kurumsal' === $item['fatura_tipi'] ? __( 'Kurumsal', 'woo-gib-efatura' ) : __( 'Bireysel', 'woo-gib-efatura' ) );
	}

	public function column_durum( array $item ): string {
		$labels = [
			WGF_Invoice_Repository::STATUS_DRAFT   => __( 'Taslak', 'woo-gib-efatura' ),
			WGF_Invoice_Repository::STATUS_SIGNED  => __( 'İmzalandı', 'woo-gib-efatura' ),
			WGF_Invoice_Repository::STATUS_ERROR   => __( 'Hata', 'woo-gib-efatura' ),
			WGF_Invoice_Repository::STATUS_DELETED => __( 'Silindi', 'woo-gib-efatura' ),
		];
		return sprintf(
			'<span class="wgf-badge wgf-badge-%1$s">%2$s</span>',
			esc_attr( $item['durum'] ),
			esc_html( $labels[ $item['durum'] ] ?? $item['durum'] )
		);
	}

	public function column_mode( array $item ): string {
		return esc_html( 'test' === $item['mode'] ? __( 'Test', 'woo-gib-efatura' ) : __( 'Canlı', 'woo-gib-efatura' ) );
	}

	public function column_created_at( array $item ): string {
		return esc_html( mysql2date( 'd.m.Y H:i', $item['created_at'] ) );
	}

	public function column_actions( array $item ): string {
		$links = [];

		if ( in_array( $item['durum'], [ WGF_Invoice_Repository::STATUS_DRAFT, WGF_Invoice_Repository::STATUS_SIGNED ], true ) ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_preview_invoice&invoice_id=' . $item['id'] ), 'wgf_preview_' . $item['id'] ) ),
				esc_html__( 'Görüntüle', 'woo-gib-efatura' )
			);
		}

		if ( WGF_Invoice_Repository::STATUS_SIGNED === $item['durum'] && ! empty( $item['dosya_yolu'] ) ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_download_invoice&invoice_id=' . $item['id'] ), 'wgf_download_' . $item['id'] ) ),
				esc_html__( 'İndir', 'woo-gib-efatura' )
			);
		}

		$order = wc_get_order( (int) $item['order_id'] );
		if ( $order ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $order->get_edit_order_url() ), esc_html__( 'Siparişi Görüntüle', 'woo-gib-efatura' ) );
		}

		return implode( ' | ', $links );
	}

	public function column_default( $item, $column_name ): string {
		return esc_html( $item[ $column_name ] ?? '' );
	}

	protected function get_default_primary_column_name(): string {
		return 'order_id';
	}
}
