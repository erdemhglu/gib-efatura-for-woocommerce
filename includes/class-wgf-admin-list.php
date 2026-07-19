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
			<h1><?php esc_html_e( 'GİB e-Fatura Kayıtları', 'gib-efatura-for-woocommerce' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="wgf-invoices" />
				<?php $table->search_box( __( 'Ara', 'gib-efatura-for-woocommerce' ), 'wgf-search' ); ?>
				<?php $table->views_list(); ?>
				<?php $table->display(); ?>
			</form>

			<div id="wgf-bulk-sign-panel" class="wgf-metabox" style="display:none;margin-top:12px;padding:12px;background:#fff;border:1px solid #ccd0d4;max-width:460px;">
				<p><strong><?php esc_html_e( 'Toplu İmzalama', 'gib-efatura-for-woocommerce' ); ?></strong></p>
				<p class="description wgf-bulk-sign-count"></p>
				<p>
					<button type="button" class="button button-primary" id="wgf-bulk-start-sms"><?php esc_html_e( 'SMS Kodu Gönder', 'gib-efatura-for-woocommerce' ); ?></button>
					<button type="button" class="button" id="wgf-bulk-cancel"><?php esc_html_e( 'Vazgeç', 'gib-efatura-for-woocommerce' ); ?></button>
					<span class="spinner wgf-spinner"></span>
				</p>
				<div class="wgf-bulk-sms-box" style="display:none;">
					<p>
						<label><?php esc_html_e( 'SMS Kodu', 'gib-efatura-for-woocommerce' ); ?></label><br />
						<input type="text" class="wgf-bulk-sms-code" />
						<button type="button" class="button button-primary" id="wgf-bulk-complete-sms"><?php esc_html_e( 'Onayla ve İmzala', 'gib-efatura-for-woocommerce' ); ?></button>
					</p>
				</div>
				<div class="wgf-message"></div>
			</div>
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
			''                                    => __( 'Tümü', 'gib-efatura-for-woocommerce' ),
			WGF_Invoice_Repository::STATUS_DRAFT  => __( 'Taslak', 'gib-efatura-for-woocommerce' ),
			WGF_Invoice_Repository::STATUS_SIGNED => __( 'İmzalandı', 'gib-efatura-for-woocommerce' ),
			WGF_Invoice_Repository::STATUS_DELETED => __( 'Silindi', 'gib-efatura-for-woocommerce' ),
		];

		$links = [];
		foreach ( $statuses as $value => $label ) {
			$url    = '' === $value ? $base : add_query_arg( 'durum', $value, $base );
			$class  = ( $current === $value ) ? ' class="current"' : '';
			$links[] = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class, esc_html( $label ) );
		}

		echo '<ul class="subsubsub"><li>' . implode( ' | </li><li>', $links ) . '</li></ul>';
	}

	protected function get_bulk_actions(): array {
		return [
			'wgf_bulk_sign'  => __( 'Seçilenleri İmzala (SMS ile)', 'gib-efatura-for-woocommerce' ),
			'wgf_bulk_purge' => __( 'Seçilenleri Kalıcı Olarak Sil', 'gib-efatura-for-woocommerce' ),
		];
	}

	/**
	 * Seçim kutusu yalnızca üzerinde bir toplu işlem uygulanabilecek satırlarda gösterilir:
	 * taslak (SMS ile imzalanabilir) veya silindi (kalıcı olarak silinebilir).
	 */
	public function column_cb( $item ): string {
		if ( ! in_array( $item['durum'], [ WGF_Invoice_Repository::STATUS_DRAFT, WGF_Invoice_Repository::STATUS_DELETED ], true ) ) {
			return '';
		}
		return sprintf( '<input type="checkbox" name="invoice[]" value="%d" />', (int) $item['id'] );
	}

	public function get_columns(): array {
		return [
			'cb'          => '<input type="checkbox" />',
			'order_id'    => __( 'Sipariş', 'gib-efatura-for-woocommerce' ),
			'alici_ad'    => __( 'Müşteri', 'gib-efatura-for-woocommerce' ),
			'fatura_tipi' => __( 'Tip', 'gib-efatura-for-woocommerce' ),
			'belge_turu'  => __( 'Belge Türü', 'gib-efatura-for-woocommerce' ),
			'belge_no'    => __( 'Belge No', 'gib-efatura-for-woocommerce' ),
			'tutar'       => __( 'Tutar', 'gib-efatura-for-woocommerce' ),
			'durum'       => __( 'Durum', 'gib-efatura-for-woocommerce' ),
			'mode'        => __( 'Ortam', 'gib-efatura-for-woocommerce' ),
			'created_at'  => __( 'Tarih', 'gib-efatura-for-woocommerce' ),
			'actions'     => __( 'İşlemler', 'gib-efatura-for-woocommerce' ),
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
		return esc_html( 'kurumsal' === $item['fatura_tipi'] ? __( 'Kurumsal', 'gib-efatura-for-woocommerce' ) : __( 'Bireysel', 'gib-efatura-for-woocommerce' ) );
	}

	public function column_belge_turu( array $item ): string {
		return 'iade' === ( $item['belge_turu'] ?? 'satis' )
			? '<span class="wgf-badge wgf-badge-iade">' . esc_html__( 'İade', 'gib-efatura-for-woocommerce' ) . '</span>'
			: esc_html__( 'Satış', 'gib-efatura-for-woocommerce' );
	}

	public function column_durum( array $item ): string {
		$labels = [
			WGF_Invoice_Repository::STATUS_DRAFT   => __( 'Taslak', 'gib-efatura-for-woocommerce' ),
			WGF_Invoice_Repository::STATUS_SIGNED  => __( 'İmzalandı', 'gib-efatura-for-woocommerce' ),
			WGF_Invoice_Repository::STATUS_ERROR   => __( 'Hata', 'gib-efatura-for-woocommerce' ),
			WGF_Invoice_Repository::STATUS_DELETED => __( 'Silindi', 'gib-efatura-for-woocommerce' ),
		];
		return sprintf(
			'<span class="wgf-badge wgf-badge-%1$s">%2$s</span>',
			esc_attr( $item['durum'] ),
			esc_html( $labels[ $item['durum'] ] ?? $item['durum'] )
		);
	}

	public function column_mode( array $item ): string {
		return esc_html( 'test' === $item['mode'] ? __( 'Test', 'gib-efatura-for-woocommerce' ) : __( 'Canlı', 'gib-efatura-for-woocommerce' ) );
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
				esc_html__( 'Görüntüle', 'gib-efatura-for-woocommerce' )
			);
		}

		if ( WGF_Invoice_Repository::STATUS_SIGNED === $item['durum'] && ! empty( $item['dosya_yolu'] ) ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_download_invoice&invoice_id=' . $item['id'] ), 'wgf_download_' . $item['id'] ) ),
				esc_html__( 'İndir', 'gib-efatura-for-woocommerce' )
			);
		}

		$order = wc_get_order( (int) $item['order_id'] );
		if ( $order ) {
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $order->get_edit_order_url() ), esc_html__( 'Siparişi Görüntüle', 'gib-efatura-for-woocommerce' ) );
		}

		if ( WGF_Invoice_Repository::STATUS_DELETED === $item['durum'] ) {
			$links[] = sprintf(
				'<a href="#" class="wgf-row-purge" data-invoice-id="%d" style="color:#a00;">%s</a>',
				(int) $item['id'],
				esc_html__( 'Kalıcı Olarak Sil', 'gib-efatura-for-woocommerce' )
			);
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
