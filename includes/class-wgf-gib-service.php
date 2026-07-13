<?php
defined( 'ABSPATH' ) || exit;

use Mlevent\Fatura\Gib;
use Mlevent\Fatura\Exceptions\FaturaException;

/**
 * Mlevent\Fatura\Gib kütüphanesine ince bir sarmalayıcı.
 * Ayarlardan okunan kimlik bilgileriyle oturum açar, istisnaları Türkçe / kullanıcıya
 * gösterilmesi güvenli mesajlara çevirir ve tüm ham hataları loglar.
 */
class WGF_Gib_Service {

	/**
	 * Ayarlardaki moda göre bağlanmış ve oturum açmış bir Gib istemcisi döndürür.
	 *
	 * @throws WGF_Exception
	 */
	public static function client(): Gib {
		$test_mode = WGF_Settings::is_test_mode();
		$username  = WGF_Settings::username();
		$password  = WGF_Settings::password();

		if ( '' === $username || '' === $password ) {
			throw new WGF_Exception(
				$test_mode
					? __( 'Test API kullanıcı bilgileri tanımlı değil. Ayarlar sayfasından "Test Kullanıcısı Al" butonuyla bir test hesabı alabilirsiniz.', 'gib-efatura-for-woocommerce' )
					: __( 'GİB canlı API kullanıcı kodu/parola bilgileri tanımlı değil. Lütfen Ayarlar sayfasından girin.', 'gib-efatura-for-woocommerce' )
			);
		}

		$gib = new Gib();
		if ( $test_mode ) {
			$gib->testMode();
		}

		try {
			$gib->login( $username, $password );
		} catch ( FaturaException $e ) {
			self::log_exception( 'login', $e );
			throw new WGF_Exception( __( 'GİB portalına giriş yapılamadı. Kullanıcı kodu/parola bilgilerinizi kontrol edin.', 'gib-efatura-for-woocommerce' ) );
		} catch ( \Throwable $e ) {
			WGF_Logger::error( 'login genel hata: ' . $e->getMessage() );
			throw new WGF_Exception( __( 'GİB portalına bağlanılamadı. Lütfen daha sonra tekrar deneyin.', 'gib-efatura-for-woocommerce' ) );
		}

		if ( ! $gib->getToken() ) {
			throw new WGF_Exception( __( 'GİB portalından oturum anahtarı (token) alınamadı.', 'gib-efatura-for-woocommerce' ) );
		}

		return $gib;
	}

	public static function logout( Gib $gib ): void {
		try {
			$gib->logout();
		} catch ( \Throwable $e ) {
			// Oturum kapatma hatası kritik değildir, sadece loglanır.
			WGF_Logger::debug( 'logout hatası: ' . $e->getMessage() );
		}
	}

	/**
	 * Test kullanıcı kodu/parolası talep eder.
	 *
	 * @return array{username:string,password:string}
	 * @throws WGF_Exception
	 */
	public static function fetch_test_credentials(): array {
		try {
			$gib = new Gib();
			return $gib->testMode()->getTestCredentials();
		} catch ( FaturaException $e ) {
			self::log_exception( 'fetch_test_credentials', $e );
			throw new WGF_Exception( __( 'Şu anda GİB test sunucusunda kullanılabilir test hesabı yok, lütfen birkaç dakika sonra tekrar deneyin.', 'gib-efatura-for-woocommerce' ) );
		}
	}

	/**
	 * Taslak fatura oluşturur ve oluşturulan UUID'yi döndürür.
	 *
	 * @throws WGF_Exception
	 */
	public static function create_draft( \Mlevent\Fatura\Interfaces\ModelInterface $model ): string {
		$gib = self::client();
		try {
			$gib->createDraft( $model );
			return $model->getUuid();
		} catch ( FaturaException $e ) {
			self::log_exception( 'create_draft', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * SMS doğrulama sürecini başlatır, işlem (operasyon) ID'sini döndürür.
	 *
	 * @throws WGF_Exception
	 */
	public static function start_sms_verification(): string {
		$gib = self::client();
		try {
			$oid = $gib->startSmsVerification();
			if ( ! $oid ) {
				throw new WGF_Exception( __( 'Portalda kayıtlı bir GSM numarası bulunamadığından SMS gönderilemedi.', 'gib-efatura-for-woocommerce' ) );
			}
			return $oid;
		} catch ( FaturaException $e ) {
			self::log_exception( 'start_sms_verification', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * SMS kodu ile belgeyi imzalar (resmi hâle getirir).
	 *
	 * @throws WGF_Exception
	 */
	public static function complete_sms_verification( string $code, string $oid, string $uuid ): bool {
		$gib = self::client();
		try {
			$result = $gib->completeSmsVerification( $code, $oid, [ $uuid ] );
			if ( ! $result ) {
				throw new WGF_Exception( __( 'SMS kodu doğrulanamadı. Kodu kontrol edip tekrar deneyin.', 'gib-efatura-for-woocommerce' ) );
			}
			return true;
		} catch ( FaturaException $e ) {
			self::log_exception( 'complete_sms_verification', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * Portaldaki belge bilgisini getirir (ör. belge numarasını öğrenmek için).
	 *
	 * @throws WGF_Exception
	 */
	public static function get_document( string $uuid ): array {
		$gib = self::client();
		try {
			return $gib->getDocument( $uuid );
		} catch ( FaturaException $e ) {
			self::log_exception( 'get_document', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * Belgenin GİB portalındaki HTML görünümünü getirir (taslak veya imzalanmış).
	 *
	 * @throws WGF_Exception
	 */
	public static function get_html( string $uuid, bool $signed ): string {
		$gib = self::client();
		try {
			$html = $gib->getHtml( $uuid, $signed );
			if ( ! $html ) {
				throw new WGF_Exception( __( 'Fatura önizlemesi alınamadı.', 'gib-efatura-for-woocommerce' ) );
			}
			return $html;
		} catch ( FaturaException $e ) {
			self::log_exception( 'get_html', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * Belgeyi sunucuya (verilen tam yola) indirir ve dosya yolunu döndürür.
	 *
	 * @throws WGF_Exception
	 */
	public static function save_to_disk( string $uuid, string $dir, string $filename ): string {
		$gib = self::client();
		try {
			$result = $gib->saveToDisk( $uuid, $dir, $filename );
			if ( ! $result ) {
				throw new WGF_Exception( __( 'Fatura dosyası indirilemedi.', 'gib-efatura-for-woocommerce' ) );
			}
			return $result;
		} catch ( FaturaException $e ) {
			self::log_exception( 'save_to_disk', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * İmzalanmış (resmi) bir belge için GİB portalına iptal başvurusu gönderir.
	 * Başvuru; alıcının GİB portalından itiraz etmemesi hâlinde yasal süre sonunda kendiliğinden onaylanır.
	 *
	 * @throws WGF_Exception
	 */
	public static function cancellation_request( string $uuid, string $explanation ): string {
		$gib = self::client();
		try {
			$result = $gib->cancellationRequest( $uuid, $explanation );
			if ( ! $result ) {
				throw new WGF_Exception( __( 'İptal başvurusu oluşturulamadı.', 'gib-efatura-for-woocommerce' ) );
			}
			return $result;
		} catch ( FaturaException $e ) {
			self::log_exception( 'cancellation_request', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	/**
	 * Yalnızca taslak (henüz imzalanmamış) bir belgeyi siler.
	 *
	 * @throws WGF_Exception
	 */
	public static function delete_draft( string $uuid ): bool {
		$gib = self::client();
		try {
			$gib->deleteDraft( [ $uuid ], __( 'WooCommerce eklentisi üzerinden silindi', 'gib-efatura-for-woocommerce' ) );
			return true;
		} catch ( FaturaException $e ) {
			self::log_exception( 'delete_draft', $e );
			throw new WGF_Exception( self::friendly_api_message( $e ) );
		} finally {
			self::logout( $gib );
		}
	}

	private static function friendly_api_message( FaturaException $e ): string {
		$message = trim( (string) $e->getMessage() );
		if ( '' === $message ) {
			return __( 'GİB portalından beklenmeyen bir yanıt alındı.', 'gib-efatura-for-woocommerce' );
		}
		// Kütüphane zaten Türkçe mesajlar döndürür, doğrudan kullanılabilir.
		return $message;
	}

	private static function log_exception( string $context, FaturaException $e ): void {
		WGF_Logger::error( sprintf( '[%s] %s', $context, $e->getMessage() ), [
			'request'  => $e->hasResponse() ? wp_json_encode( $e->getRequest() ) : null,
			'response' => $e->hasResponse() ? wp_json_encode( $e->getResponse() ) : null,
		] );
	}
}
