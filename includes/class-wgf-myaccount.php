<?php
defined( 'ABSPATH' ) || exit;

/**
 * Müşteri "Hesabım" alanında fatura görüntüleme/indirme bağlantıları.
 */
class WGF_MyAccount {

	public static function register_hooks(): void {
		add_action( 'woocommerce_order_details_after_order_table', [ __CLASS__, 'render_on_order_details' ] );
		add_filter( 'woocommerce_my_account_my_orders_columns', [ __CLASS__, 'add_orders_column' ] );
		add_action( 'woocommerce_my_account_my_orders_column_wgf_invoice', [ __CLASS__, 'render_orders_column' ] );
	}

	private static function enabled(): bool {
		return (bool) WGF_Settings::get( 'customer_download' );
	}

	private static function download_link( array $invoice ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=wgf_download_invoice_customer&invoice_id=' . $invoice['id'] ),
			'wgf_customer_download_' . $invoice['id']
		);
	}

	public static function render_on_order_details( \WC_Order $order ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$invoice = WGF_Invoice_Repository::find_active_by_order( $order->get_id() );
		if ( ! $invoice || WGF_Invoice_Repository::STATUS_SIGNED !== $invoice['durum'] || empty( $invoice['dosya_yolu'] ) ) {
			return;
		}

		echo '<section class="wgf-customer-invoice" style="margin-top:20px;">';
		echo '<h2>' . esc_html__( 'e-Fatura', 'gib-efatura-for-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Bu siparişe ait e-Faturanızı aşağıdaki bağlantıdan indirebilirsiniz.', 'gib-efatura-for-woocommerce' ) . '</p>';
		printf(
			'<a class="button" href="%s">%s</a>',
			esc_url( self::download_link( $invoice ) ),
			esc_html__( 'Faturayı İndir', 'gib-efatura-for-woocommerce' )
		);
		echo '</section>';
	}

	public static function add_orders_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order-actions' === $key ) {
				$new['wgf_invoice'] = __( 'Fatura', 'gib-efatura-for-woocommerce' );
			}
		}
		if ( ! isset( $new['wgf_invoice'] ) ) {
			$new['wgf_invoice'] = __( 'Fatura', 'gib-efatura-for-woocommerce' );
		}
		return $new;
	}

	public static function render_orders_column( \WC_Order $order ): void {
		if ( ! self::enabled() ) {
			echo '&mdash;';
			return;
		}

		$invoice = WGF_Invoice_Repository::find_active_by_order( $order->get_id() );
		if ( ! $invoice || WGF_Invoice_Repository::STATUS_SIGNED !== $invoice['durum'] || empty( $invoice['dosya_yolu'] ) ) {
			echo '&mdash;';
			return;
		}

		printf(
			'<a href="%s">%s</a>',
			esc_url( self::download_link( $invoice ) ),
			esc_html__( 'İndir', 'gib-efatura-for-woocommerce' )
		);
	}
}
