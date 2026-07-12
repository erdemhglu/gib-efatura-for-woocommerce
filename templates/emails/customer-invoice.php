<?php
/**
 * GİB e-Fatura müşteri e-postası (HTML).
 *
 * @var WC_Order $order
 * @var array    $invoice
 * @var string   $email_heading
 * @var string   $additional_content
 * @var WC_Email $email
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
?>

<p>
	<?php
	printf(
		/* translators: 1: müşteri adı 2: sipariş numarası */
		esc_html__( 'Merhaba %1$s, %2$s numaralı siparişiniz için düzenlenen e-Fatura ekte yer almaktadır.', 'woo-gib-efatura' ),
		esc_html( $order->get_billing_first_name() ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin:12px 0;">
	<tr>
		<td><strong><?php esc_html_e( 'Belge No', 'woo-gib-efatura' ); ?></strong></td>
		<td><?php echo esc_html( $invoice['belge_no'] ?? '-' ); ?></td>
	</tr>
	<tr>
		<td><strong><?php esc_html_e( 'Tutar', 'woo-gib-efatura' ); ?></strong></td>
		<td><?php echo esc_html( number_format_i18n( (float) ( $invoice['tutar'] ?? 0 ), 2 ) . ' ' . ( $invoice['para_birimi'] ?? '' ) ); ?></td>
	</tr>
	<tr>
		<td><strong><?php esc_html_e( 'Sipariş No', 'woo-gib-efatura' ); ?></strong></td>
		<td><?php echo esc_html( $order->get_order_number() ); ?></td>
	</tr>
</table>

<?php if ( $additional_content ) : ?>
	<p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
