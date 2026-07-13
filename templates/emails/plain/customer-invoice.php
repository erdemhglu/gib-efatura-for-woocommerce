<?php
/**
 * GİB e-Fatura müşteri e-postası (düz metin).
 *
 * @var WC_Order $order
 * @var array    $invoice
 * @var string   $email_heading
 * @var string   $additional_content
 */
defined( 'ABSPATH' ) || exit;

echo wp_strip_all_tags( $email_heading ) . "\n\n";

printf(
	/* translators: 1: müşteri adı 2: sipariş numarası */
	esc_html__( 'Merhaba %1$s, %2$s numaralı siparişiniz için düzenlenen e-Fatura ekte yer almaktadır.', 'gib-efatura-for-woocommerce' ),
	$order->get_billing_first_name(),
	$order->get_order_number()
);

echo "\n\n----------------------------------------\n\n";

echo esc_html__( 'Belge No', 'gib-efatura-for-woocommerce' ) . ': ' . ( $invoice['belge_no'] ?? '-' ) . "\n";
echo esc_html__( 'Tutar', 'gib-efatura-for-woocommerce' ) . ': ' . number_format_i18n( (float) ( $invoice['tutar'] ?? 0 ), 2 ) . ' ' . ( $invoice['para_birimi'] ?? '' ) . "\n";
echo esc_html__( 'Sipariş No', 'gib-efatura-for-woocommerce' ) . ': ' . $order->get_order_number() . "\n";

echo "\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo wp_strip_all_tags( $additional_content ) . "\n\n";
}
