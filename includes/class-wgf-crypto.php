<?php
defined( 'ABSPATH' ) || exit;

/**
 * GİB portal parolası gibi hassas ayarları veritabanında düz metin yerine
 * şifrelenmiş olarak saklamak için basit bir yardımcı sınıf.
 *
 * Not: Anahtar, kurulumun kendi AUTH_KEY/AUTH_SALT değerlerinden türetilir.
 * Bu; verinin tesadüfen (ör. yedek dosyası paylaşımı) sızmasına karşı bir önlemdir,
 * WordPress kurulumunun kendisi ele geçirilirse (wp-config.php dahil) koruma sağlamaz.
 */
class WGF_Crypto {

	private static function key(): string {
		$secret = ( defined( 'AUTH_KEY' ) && AUTH_KEY ) ? AUTH_KEY : 'woo-gib-efatura';
		$salt   = ( defined( 'AUTH_SALT' ) && AUTH_SALT ) ? AUTH_SALT : 'woo-gib-efatura-salt';
		return hash( 'sha256', $secret . $salt, true );
	}

	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// OpenSSL yoksa geri dönüş olarak base64 (şifreleme değildir, sadece taşıma amaçlıdır).
			return 'b64:' . base64_encode( $plain );
		}
		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $encrypted ) {
			return 'b64:' . base64_encode( $plain );
		}
		return 'enc:' . base64_encode( $iv . $encrypted );
	}

	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		if ( str_starts_with( $value, 'b64:' ) ) {
			return base64_decode( substr( $value, 4 ) ) ?: '';
		}
		if ( ! str_starts_with( $value, 'enc:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $value, 4 ) );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv        = substr( $raw, 0, 16 );
		$encrypted = substr( $raw, 16 );
		$decrypted = openssl_decrypt( $encrypted, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $decrypted ? '' : $decrypted;
	}
}
