<?php
defined( 'ABSPATH' ) || exit;

use Mlevent\Fatura\Enums\Currency;
use Mlevent\Fatura\Enums\InvoiceType;
use Mlevent\Fatura\Enums\Unit;
use Mlevent\Fatura\Models\InvoiceModel;
use Mlevent\Fatura\Models\InvoiceItemModel;
use Mlevent\Fatura\Models\InvoiceReturnItemModel;

/**
 * WC_Order verisini Mlevent\Fatura InvoiceModel'e dönüştürür.
 * Sipariş/checkout verisinde bulunmayan zorunlu GİB alanları için
 * ayarlardaki alan eşleştirmesi ve varsayılan değerler kullanılır.
 */
class WGF_Order_Builder {

	private const ALLOWED_KDV_RATES = [ 0, 1, 8, 10, 18, 20 ];

	/**
	 * Fatura oluşturma öncesi, admin ekranında düzenlenebilir alanların
	 * siparişten türetilmiş varsayılan değerlerini döndürür.
	 */
	public static function get_defaults( \WC_Order $order ): array {
		$company = trim( (string) $order->get_billing_company() );
		$tax_id  = self::resolve_field( $order, WGF_Settings::field_map( 'field_map_tax_id' ), (string) WGF_Settings::get( 'default_tax_id', '11111111111' ) );
		$tax_id  = preg_replace( '/\D/', '', $tax_id );

		$district = self::resolve_field(
			$order,
			WGF_Settings::field_map( 'field_map_district' ),
			trim( (string) $order->get_billing_address_2() ) ?: (string) WGF_Settings::get( 'default_district', 'Merkez' )
		);

		$tax_office = self::resolve_field( $order, WGF_Settings::field_map( 'field_map_tax_office' ), '' );

		return [
			'aliciAdi'        => $order->get_billing_first_name() ?: __( 'Müşteri', 'gib-efatura-for-woocommerce' ),
			'aliciSoyadi'     => $order->get_billing_last_name() ?: '-',
			'aliciUnvan'      => $company,
			'vknTckn'         => $tax_id ?: '11111111111',
			'vergiDairesi'    => $tax_office,
			'adres'           => trim( $order->get_billing_address_1() ),
			'mahalleSemtIlce' => $district,
			'sehir'           => $order->get_billing_city() ?: $order->get_billing_state(),
			'ulke'            => self::map_country( $order->get_billing_country() ),
			'postaKodu'       => $order->get_billing_postcode(),
			'tel'             => $order->get_billing_phone(),
			'eposta'          => $order->get_billing_email(),
			'paraBirimi'      => $order->get_currency(),
			'dovizKuru'       => 0,
			'not'             => self::render_note( (string) WGF_Settings::get( 'default_note', '' ), $order ),
			'faturaTipi'      => self::detect_invoice_type( $tax_id, $company ),
			'irsaliyeNumarasi' => '',
			'irsaliyeTarihi'   => '',
			// Fatura tarihi, siparişin verildiği tarihten farklı olabilir (ör. irsaliye bu ay
			// kesilip fatura sonraki ay düzenlenebilir); bu yüzden varsayılan olarak "bugün"
			// gösterilir ama fatura oluşturma formunda düzenlenebilir.
			'faturaTarihi'    => current_time( 'd/m/Y' ),
		];
	}

	/**
	 * @param array{faturaNo:string,tarihi:string,kalemler:array<int,float>}|null $return_info İade faturası oluşturuluyorsa,
	 *        iadeye konu orijinal faturanın belge no/tarihi ve iade edilecek sipariş kalemi ID => miktar eşleşmesi.
	 *
	 * @throws WGF_Exception
	 */
	public static function build( \WC_Order $order, array $overrides = [], ?array $return_info = null ): InvoiceModel {
		$data = array_merge( self::get_defaults( $order ), array_filter( $overrides, fn( $v ) => null !== $v && '' !== $v ) );

		$currency = Currency::tryFrom( strtoupper( (string) $data['paraBirimi'] ) ) ?? Currency::TRY;
		$rate     = (float) ( $overrides['dovizKuru'] ?? $data['dovizKuru'] ?? 0 );

		if ( Currency::TRY !== $currency && $rate <= 0 ) {
			throw new WGF_Exception( __( 'Sipariş TRY dışında bir para biriminde. Fatura oluşturmak için döviz kurunu girmelisiniz.', 'gib-efatura-for-woocommerce' ) );
		}

		$invoice = new InvoiceModel(
			vknTckn         : (string) $data['vknTckn'],
			tarih           : (string) $data['faturaTarihi'],
			paraBirimi      : $currency,
			dovizKuru       : Currency::TRY === $currency ? 0 : $rate,
			faturaTipi      : $return_info ? InvoiceType::Iade : InvoiceType::Satis,
			siparisNumarasi : (string) $order->get_order_number(),
			siparisTarihi   : $order->get_date_created() ? $order->get_date_created()->date( 'd/m/Y' ) : current_time( 'd/m/Y' ),
			aliciUnvan      : (string) $data['aliciUnvan'],
			aliciAdi        : (string) $data['aliciAdi'],
			aliciSoyadi     : (string) $data['aliciSoyadi'],
			adres           : (string) $data['adres'],
			mahalleSemtIlce : (string) $data['mahalleSemtIlce'],
			sehir           : (string) $data['sehir'],
			ulke            : (string) $data['ulke'],
			postaKodu       : (string) $data['postaKodu'],
			tel             : (string) $data['tel'],
			eposta          : (string) $data['eposta'],
			vergiDairesi    : (string) $data['vergiDairesi'],
			not             : (string) $data['not'],
			irsaliyeNumarasi: (string) $data['irsaliyeNumarasi'],
			irsaliyeTarihi  : (string) $data['irsaliyeTarihi'],
		);

		foreach ( self::build_items( $order, $return_info['kalemler'] ?? null ) as $item ) {
			$invoice->addItem( $item );
		}

		if ( ! $invoice->getItems() ) {
			throw new WGF_Exception( $return_info
				? __( 'İade için seçilen kalemler bulunamadı.', 'gib-efatura-for-woocommerce' )
				: __( 'Siparişte faturalandırılacak kalem bulunamadı.', 'gib-efatura-for-woocommerce' )
			);
		}

		if ( $return_info ) {
			$invoice->addReturnItem( new InvoiceReturnItemModel(
				faturaNo        : (string) $return_info['faturaNo'],
				duzenlenmeTarihi: (string) $return_info['tarihi'],
			) );
		}

		return $invoice;
	}

	/**
	 * Siparişteki faturalandırılabilir kalemleri, admin ekranında iade seçimi
	 * yaparken göstermek üzere basit bir listeye döker.
	 *
	 * @return array<int,array{name:string,qty:float,unit_price:float,currency:string}>
	 */
	public static function get_returnable_items( \WC_Order $order ): array {
		$items = [];
		foreach ( $order->get_items( [ 'line_item', 'shipping', 'fee' ] ) as $item_id => $order_item ) {
			$subtotal = (float) ( $order_item instanceof \WC_Order_Item_Product ? $order_item->get_subtotal() : $order_item->get_total() );
			if ( 0.0 === round( $subtotal, 2 ) ) {
				continue;
			}
			$qty = $order_item instanceof \WC_Order_Item_Product ? max( 1.0, (float) $order_item->get_quantity() ) : 1.0;

			$items[ $item_id ] = [
				'name'       => $order_item->get_name() ?: __( 'Ürün', 'gib-efatura-for-woocommerce' ),
				'qty'        => $qty,
				'unit_price' => round( $subtotal / $qty, 4 ),
				'currency'   => $order->get_currency(),
			];
		}
		return $items;
	}

	/**
	 * @param array<int,float>|null $item_quantities Sadece belirtilen sipariş kalemi ID => miktar
	 *        eşleşmesindeki kalemleri (miktar sınırlı olarak) dahil eder; null ise tüm kalemler tam miktarıyla dahil edilir.
	 *
	 * @return InvoiceItemModel[]
	 */
	private static function build_items( \WC_Order $order, ?array $item_quantities = null ): array {
		$items = [];
		$unit  = self::resolve_unit( (string) WGF_Settings::get( 'default_unit', 'Adet' ) );

		foreach ( $order->get_items( [ 'line_item', 'shipping', 'fee' ] ) as $item_id => $order_item ) {
			if ( null !== $item_quantities ) {
				if ( empty( $item_quantities[ $item_id ] ) || $item_quantities[ $item_id ] <= 0 ) {
					continue;
				}
			}

			$qty_override = null !== $item_quantities ? (float) $item_quantities[ $item_id ] : null;

			$item = self::build_item_from_order_item( $order_item, $unit, $qty_override );
			if ( $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	private static function build_item_from_order_item( \WC_Order_Item $order_item, Unit $default_unit, ?float $qty_override = null ): ?InvoiceItemModel {
		$name = $order_item->get_name();

		if ( $order_item instanceof \WC_Order_Item_Product ) {
			$orig_qty = max( 1.0, (float) $order_item->get_quantity() );
			$qty      = null !== $qty_override ? min( $qty_override, $orig_qty ) : $orig_qty;
			$ratio    = $qty / $orig_qty;
			$subtotal = (float) $order_item->get_subtotal() * $ratio;
			$total    = (float) $order_item->get_total() * $ratio;
			$tax      = (float) $order_item->get_subtotal_tax() * $ratio;
			$unit     = self::product_unit( $order_item->get_product() ) ?? $default_unit;
		} elseif ( $order_item instanceof \WC_Order_Item_Shipping ) {
			$qty      = 1.0;
			$subtotal = (float) $order_item->get_total();
			$total    = $subtotal;
			$tax      = (float) $order_item->get_total_tax();
			$unit     = Unit::Adet;
			$name     = $name ?: __( 'Kargo/Teslimat', 'gib-efatura-for-woocommerce' );
		} elseif ( $order_item instanceof \WC_Order_Item_Fee ) {
			$qty      = 1.0;
			$subtotal = (float) $order_item->get_total();
			$total    = $subtotal;
			$tax      = (float) $order_item->get_total_tax();
			$unit     = Unit::Adet;
		} else {
			return null;
		}

		if ( 0.0 === round( $subtotal, 2 ) ) {
			return null;
		}

		// Ödeme altyapısının KDV hesaplamadan eklediği kalemler (taksit farkı, POS ücreti vb.):
		// kalem adı ayarlardaki regex kalıplarından biriyle eşleşiyorsa, mevcut tutar KDV dahil
		// kabul edilip KDV hariç tutar geriye doğru hesaplanır.
		$vat_included_rate = self::vat_included_rate_for_name( (string) $name );
		if ( null !== $vat_included_rate ) {
			$gross    = $total;
			$subtotal = $vat_included_rate > 0 ? round( $gross / ( 1 + $vat_included_rate / 100 ), 4 ) : $gross;
			$total    = $subtotal;
			$tax      = round( $gross - $subtotal, 2 );
		}

		$unit_price = round( $subtotal / $qty, 4 );
		$discount   = round( $subtotal - $total, 2 );
		$kdv_rate   = null !== $vat_included_rate
			? self::nearest_kdv_rate( $vat_included_rate )
			: self::nearest_kdv_rate( $subtotal > 0 ? ( $tax / $subtotal * 100 ) : 0.0 );

		$args = [
			'malHizmet'  => $name ?: __( 'Ürün', 'gib-efatura-for-woocommerce' ),
			'miktar'     => $qty,
			'birimFiyat' => $unit_price,
			'kdvOrani'   => (float) $kdv_rate,
			'birim'      => $unit,
		];

		if ( $discount > 0 ) {
			$args['iskontoTipi']   = 'İskonto';
			$args['iskontoTutari'] = $discount;
		} elseif ( $discount < 0 ) {
			$args['iskontoTipi']   = 'Arttırım';
			$args['iskontoTutari'] = abs( $discount );
		}

		return new InvoiceItemModel( ...$args );
	}

	/**
	 * Kalem adı, ayarlarda tanımlı "KDV dahil fiyat" regex kalıplarından biriyle
	 * eşleşiyorsa uygulanacak KDV oranını, eşleşmiyorsa null döndürür.
	 */
	private static function vat_included_rate_for_name( string $name ): ?float {
		if ( '' === trim( $name ) ) {
			return null;
		}

		foreach ( self::vat_included_patterns() as $pattern ) {
			$regex = '~' . $pattern . '~iu';
			if ( false === @preg_match( $regex, $name ) ) {
				continue; // Geçersiz regex, sessizce atla.
			}
			if ( 1 === preg_match( $regex, $name ) ) {
				return max( 0.0, (float) WGF_Settings::get( 'vat_included_rate', 20 ) );
			}
		}

		return null;
	}

	/** @return string[] */
	private static function vat_included_patterns(): array {
		$raw   = (string) WGF_Settings::get( 'vat_included_item_patterns', '' );
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	private static function nearest_kdv_rate( float $percent ): int {
		$closest = self::ALLOWED_KDV_RATES[0];
		$diff    = abs( $percent - $closest );
		foreach ( self::ALLOWED_KDV_RATES as $rate ) {
			$d = abs( $percent - $rate );
			if ( $d < $diff ) {
				$closest = $rate;
				$diff    = $d;
			}
		}
		return $closest;
	}

	private static function resolve_unit( string $name ): Unit {
		foreach ( Unit::cases() as $case ) {
			if ( $case->name === $name ) {
				return $case;
			}
		}
		return Unit::Adet;
	}

	private static function product_unit( $product ): ?Unit {
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}
		$meta = $product->get_meta( '_wgf_unit', true );
		return $meta ? self::resolve_unit( (string) $meta ) : null;
	}

	/**
	 * Siparişte / müşteri profilinde verilen anahtar listesini sırayla dener,
	 * ilk dolu değeri döndürür (checkout'a farklı eklentilerle eklenmiş alanları eşleştirmek için).
	 */
	private static function resolve_field( \WC_Order $order, array $keys, string $fallback = '' ): string {
		foreach ( $keys as $key ) {
			$value = $order->get_meta( $key, true );
			if ( '' === $value && $order->get_customer_id() ) {
				$value = get_user_meta( $order->get_customer_id(), $key, true );
			}
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}
		return $fallback;
	}

	private static function detect_invoice_type( string $tax_id, string $company ): string {
		return ( '' !== $company && 1 === preg_match( '/^\d{10}$/', $tax_id ) ) ? 'kurumsal' : 'bireysel';
	}

	private static function map_country( string $code ): string {
		if ( ! $code ) {
			return (string) WGF_Settings::get( 'default_country', 'Türkiye' );
		}
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$countries = WC()->countries->get_countries();
			if ( isset( $countries[ $code ] ) ) {
				return wp_strip_all_tags( $countries[ $code ] );
			}
		}
		return $code;
	}

	private static function render_note( string $template, \WC_Order $order ): string {
		if ( '' === trim( $template ) ) {
			return '';
		}
		return strtr( $template, [
			'{siparis_no}'     => (string) $order->get_order_number(),
			'{magaza_adi}'     => get_bloginfo( 'name' ),
			'{tarih}'          => current_time( 'd/m/Y' ),
			'{musteri_adi}'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'{odeme_sekli}'    => $order->get_payment_method_title() ?: '-',
			'{gonderim_sekli}' => $order->get_shipping_method() ?: '-',
		] );
	}
}
