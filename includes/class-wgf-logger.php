<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce loglama sistemine ince bir sarmalayıcı.
 * Log kayıtları: WooCommerce > Durum > Loglar altında "gib-efatura-for-woocommerce" kaynağıyla görünür.
 */
class WGF_Logger {

	private const SOURCE = 'gib-efatura-for-woocommerce';

	private static function logger(): \WC_Logger_Interface {
		return wc_get_logger();
	}

	public static function info( string $message, array $context = [] ): void {
		self::logger()->info( $message, array_merge( [ 'source' => self::SOURCE ], $context ) );
	}

	public static function error( string $message, array $context = [] ): void {
		self::logger()->error( $message, array_merge( [ 'source' => self::SOURCE ], $context ) );
	}

	public static function debug( string $message, array $context = [] ): void {
		self::logger()->debug( $message, array_merge( [ 'source' => self::SOURCE ], $context ) );
	}
}
