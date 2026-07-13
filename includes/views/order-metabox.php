<?php
/**
 * @var WC_Order   $order
 * @var array|null $invoice
 * @var array      $defaults
 * @var array      $returns     $invoice varsa, ona karşı oluşturulmuş iade faturaları.
 * @var array      $returnable  $invoice imzalanmışsa, iade formunda gösterilecek sipariş kalemleri.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wgf-metabox" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">

	<?php if ( $invoice ) : ?>

		<?php
		$is_source = true;
		include WGF_PATH . 'includes/views/order-metabox-invoice.php';
		?>

		<?php if ( $returns ) : ?>
			<h4 style="margin:14px 0 4px;"><?php esc_html_e( 'İade Faturaları', 'gib-efatura-for-woocommerce' ); ?></h4>
			<?php foreach ( $returns as $return_invoice ) : ?>
				<div class="wgf-return-item" style="margin-bottom:10px;padding:8px;background:#f6f7f7;border-radius:4px;">
					<?php
					$invoice   = $return_invoice;
					$is_source = false;
					include WGF_PATH . 'includes/views/order-metabox-invoice.php';
					?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

	<?php else : ?>

		<button type="button" class="button button-primary" id="wgf-toggle-form" data-irsaliye="0"><?php esc_html_e( 'Fatura Oluştur', 'gib-efatura-for-woocommerce' ); ?></button>
		<button type="button" class="button" id="wgf-toggle-form-irsaliye" data-irsaliye="1"><?php esc_html_e( 'İrsaliyeli Fatura Oluştur', 'gib-efatura-for-woocommerce' ); ?></button>

		<div id="wgf-create-form" style="display:none;margin-top:10px;">
			<p class="description"><?php esc_html_e( 'Aşağıdaki bilgiler siparişten otomatik dolduruldu, gerekirse düzenleyebilirsiniz.', 'gib-efatura-for-woocommerce' ); ?></p>

			<p>
				<label><?php esc_html_e( 'Fatura Tarihi', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="faturaTarihi" value="<?php echo esc_attr( $defaults['faturaTarihi'] ); ?>" placeholder="gg/aa/yyyy" />
				<span class="description"><?php esc_html_e( 'Varsayılan bugündür. Siparişten farklı bir ayda fatura kesecekseniz (irsaliye önce, fatura sonra kesildiyse) burayı güncelleyin — kanunen irsaliye tarihinden en geç 7 gün sonrasına kadar fatura kesilmelidir.', 'gib-efatura-for-woocommerce' ); ?></span>
			</p>
			<p>
				<label><?php esc_html_e( 'Fatura Tipi', 'gib-efatura-for-woocommerce' ); ?></label><br />
				<span class="wgf-fatura-tipi-label"><?php echo 'kurumsal' === $defaults['faturaTipi'] ? esc_html__( 'Kurumsal (Unvan + VKN)', 'gib-efatura-for-woocommerce' ) : esc_html__( 'Bireysel (Ad Soyad + TCKN)', 'gib-efatura-for-woocommerce' ); ?></span>
			</p>
			<p>
				<label><?php esc_html_e( 'Ad', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="aliciAdi" value="<?php echo esc_attr( $defaults['aliciAdi'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Soyad', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="aliciSoyadi" value="<?php echo esc_attr( $defaults['aliciSoyadi'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Firma Unvanı (kurumsal için)', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="aliciUnvan" value="<?php echo esc_attr( $defaults['aliciUnvan'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'TC Kimlik / Vergi No', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="vknTckn" value="<?php echo esc_attr( $defaults['vknTckn'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Vergi Dairesi', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="vergiDairesi" value="<?php echo esc_attr( $defaults['vergiDairesi'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Adres', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="adres" value="<?php echo esc_attr( $defaults['adres'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Mahalle/Semt/İlçe', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="mahalleSemtIlce" value="<?php echo esc_attr( $defaults['mahalleSemtIlce'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Şehir', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="sehir" value="<?php echo esc_attr( $defaults['sehir'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Ülke', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat" name="ulke" value="<?php echo esc_attr( $defaults['ulke'] ); ?>" />
			</p>

			<?php if ( $order->get_currency() !== 'TRY' ) : ?>
				<p>
					<label><?php echo esc_html( sprintf( __( 'Döviz Kuru (%s → TRY)', 'gib-efatura-for-woocommerce' ), $order->get_currency() ) ); ?></label>
					<input type="number" step="0.0001" min="0" class="widefat" name="dovizKuru" value="" required />
				</p>
			<?php endif; ?>

			<div id="wgf-irsaliye-fields" style="display:none;">
				<p>
					<label><?php esc_html_e( 'İrsaliye No', 'gib-efatura-for-woocommerce' ); ?></label>
					<input type="text" class="widefat" name="irsaliyeNumarasi" value="<?php echo esc_attr( $defaults['irsaliyeNumarasi'] ); ?>" />
				</p>
				<p>
					<label><?php esc_html_e( 'İrsaliye Tarihi', 'gib-efatura-for-woocommerce' ); ?></label>
					<input type="text" class="widefat" name="irsaliyeTarihi" value="<?php echo esc_attr( $defaults['irsaliyeTarihi'] ); ?>" placeholder="gg/aa/yyyy" />
				</p>
			</div>

			<p>
				<label><?php esc_html_e( 'Açıklama / Not', 'gib-efatura-for-woocommerce' ); ?></label>
				<textarea class="widefat" name="not" rows="3"><?php echo esc_textarea( $defaults['not'] ); ?></textarea>
			</p>

			<button type="button" class="button button-primary wgf-btn" data-action="create_invoice"><?php esc_html_e( 'Faturayı Oluştur', 'gib-efatura-for-woocommerce' ); ?></button>
		</div>

	<?php endif; ?>

	<span class="spinner wgf-spinner"></span>
	<div class="wgf-message"></div>
</div>
