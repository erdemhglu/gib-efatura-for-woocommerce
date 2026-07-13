<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

/**
 * Fatura oluşturulup imzalandığında (veya "Faturayı Gönder" ile manuel olarak)
 * müşteriye gönderilen e-posta. WooCommerce > Ayarlar > E-postalar altında görünür,
 * konu/başlık/ek içerik oradan özelleştirilebilir.
 */
class WGF_Email extends \WC_Email {

	/** @var array|null */
	public $invoice;

	public function __construct() {
		$this->id             = 'wgf_invoice_email';
		$this->title          = __( 'GİB e-Fatura', 'gib-efatura-for-woocommerce' );
		$this->description    = __( 'Sipariş için GİB e-Faturası imzalandığında (veya "Faturayı Gönder" butonuyla manuel olarak) müşteriye gönderilir.', 'gib-efatura-for-woocommerce' );
		$this->customer_email = true;

		$this->template_html  = 'emails/customer-invoice.php';
		$this->template_plain = 'emails/plain/customer-invoice.php';
		$this->template_base  = WGF_PATH . 'templates/';

		$this->placeholders = [
			'{order_date}'   => '',
			'{order_number}' => '',
		];

		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( '{site_title} - Siparişiniz için e-Faturanız (#{order_number})', 'gib-efatura-for-woocommerce' );
	}

	public function get_default_heading(): string {
		return __( 'Faturanız Hazır', 'gib-efatura-for-woocommerce' );
	}

	/**
	 * @throws WGF_Exception
	 */
	public function trigger( \WC_Order $order, array $invoice ): void {
		$this->object  = $order;
		$this->invoice = $invoice;

		$this->recipient = $order->get_billing_email();

		if ( ! $this->get_recipient() ) {
			throw new WGF_Exception( __( 'Siparişte müşteri e-posta adresi bulunamadığından fatura gönderilemedi.', 'gib-efatura-for-woocommerce' ) );
		}

		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{order_number}'] = $order->get_order_number();

		$attachments = [];
		if ( ! empty( $invoice['dosya_yolu'] ) && file_exists( $invoice['dosya_yolu'] ) ) {
			$attachments[] = $invoice['dosya_yolu'];
		}

		$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $attachments );

		if ( ! $sent ) {
			throw new WGF_Exception( __( 'E-posta gönderilemedi. Sunucunuzun e-posta gönderim ayarlarını kontrol edin.', 'gib-efatura-for-woocommerce' ) );
		}
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'              => $this->object,
				'invoice'            => $this->invoice,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}

	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'              => $this->object,
				'invoice'            => $this->invoice,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			],
			'',
			$this->template_base
		);
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'            => [
				'title'   => __( 'Etkinleştir/Devre Dışı Bırak', 'gib-efatura-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Bu e-postayı etkinleştir', 'gib-efatura-for-woocommerce' ),
				'default' => 'yes',
			],
			'subject'            => [
				'title'       => __( 'Konu', 'gib-efatura-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => sprintf( __( 'Kullanılabilir yer tutucular: %s', 'gib-efatura-for-woocommerce' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			],
			'heading'            => [
				'title'       => __( 'Başlık', 'gib-efatura-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => sprintf( __( 'Kullanılabilir yer tutucular: %s', 'gib-efatura-for-woocommerce' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			],
			'additional_content' => [
				'title'       => __( 'Ek İçerik', 'gib-efatura-for-woocommerce' ),
				'description' => __( 'E-posta gövdesinin altına eklenecek isteğe bağlı ek metin.', 'gib-efatura-for-woocommerce' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'İlginiz için teşekkür ederiz.', 'gib-efatura-for-woocommerce' ),
				'type'        => 'textarea',
				'default'     => '',
				'desc_tip'    => true,
			],
			'email_type'         => [
				'title'       => __( 'E-posta biçimi', 'gib-efatura-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Bu e-postanın gönderim biçimini belirler.', 'gib-efatura-for-woocommerce' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}
}
