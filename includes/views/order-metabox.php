<?php
/**
 * @var WC_Order   $order
 * @var array|null $invoice
 * @var array      $defaults
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wgf-metabox" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">

	<?php if ( $invoice ) : ?>

		<?php if ( WGF_Invoice_Repository::STATUS_SIGNED === $invoice['durum'] ) : ?>
			<p><span class="wgf-badge wgf-badge-imzalandi"><?php esc_html_e( 'İmzalandı (Resmi)', 'woo-gib-efatura' ); ?></span></p>
			<p><strong><?php esc_html_e( 'Belge No:', 'woo-gib-efatura' ); ?></strong> <?php echo esc_html( $invoice['belge_no'] ?: '-' ); ?></p>
		<?php else : ?>
			<p><span class="wgf-badge wgf-badge-taslak"><?php esc_html_e( 'Taslak (İmzalanmadı)', 'woo-gib-efatura' ); ?></span></p>
			<?php if ( 'test' === $invoice['mode'] ) : ?>
				<p class="description"><?php esc_html_e( 'Test API ile oluşturuldu. Test hesaplarında SMS ile imzalama yapılamaz, bu nedenle resmi geçerliliği yoktur.', 'woo-gib-efatura' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<p>
			<strong><?php esc_html_e( 'Ortam:', 'woo-gib-efatura' ); ?></strong>
			<?php echo esc_html( 'test' === $invoice['mode'] ? __( 'Test', 'woo-gib-efatura' ) : __( 'Canlı', 'woo-gib-efatura' ) ); ?><br />
			<strong><?php esc_html_e( 'Tutar:', 'woo-gib-efatura' ); ?></strong>
			<?php echo esc_html( number_format_i18n( (float) $invoice['tutar'], 2 ) . ' ' . $invoice['para_birimi'] ); ?><br />
			<strong><?php esc_html_e( 'Alıcı:', 'woo-gib-efatura' ); ?></strong> <?php echo esc_html( $invoice['alici_ad'] ); ?><br />
			<strong><?php esc_html_e( 'İrsaliye:', 'woo-gib-efatura' ); ?></strong>
			<?php echo $invoice['irsaliye_no'] ? esc_html( $invoice['irsaliye_no'] . ( $invoice['irsaliye_tarihi'] ? ' (' . $invoice['irsaliye_tarihi'] . ')' : '' ) ) : esc_html__( 'yok', 'woo-gib-efatura' ); ?><br />
			<strong>UUID:</strong> <code style="word-break:break-all;font-size:10px;"><?php echo esc_html( $invoice['uuid'] ); ?></code>
		</p>

		<p>
			<a class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_preview_invoice&invoice_id=' . $invoice['id'] ), 'wgf_preview_' . $invoice['id'] ) ); ?>">
				<?php esc_html_e( 'Faturayı Görüntüle (Önizleme)', 'woo-gib-efatura' ); ?>
			</a>
		</p>

		<div class="wgf-actions" data-invoice-id="<?php echo esc_attr( $invoice['id'] ); ?>">
			<?php if ( WGF_Invoice_Repository::STATUS_DRAFT === $invoice['durum'] ) : ?>
				<?php if ( 'canli' === $invoice['mode'] ) : ?>
					<button type="button" class="button button-primary wgf-btn" data-action="start_sms"><?php esc_html_e( 'SMS ile İmzala', 'woo-gib-efatura' ); ?></button>
				<?php endif; ?>
				<?php if ( empty( $invoice['irsaliye_no'] ) ) : ?>
					<button type="button" class="button" id="wgf-toggle-irsaliye-add"><?php esc_html_e( 'İrsaliye Oluştur', 'woo-gib-efatura' ); ?></button>
				<?php endif; ?>
				<button type="button" class="button wgf-btn" data-action="delete_draft"><?php esc_html_e( 'Taslağı Sil', 'woo-gib-efatura' ); ?></button>

				<div class="wgf-sms-box" style="display:none;margin-top:8px;">
					<input type="text" class="small-text wgf-sms-code" placeholder="<?php esc_attr_e( 'SMS Kodu', 'woo-gib-efatura' ); ?>" />
					<button type="button" class="button button-primary wgf-btn" data-action="complete_sms"><?php esc_html_e( 'Doğrula', 'woo-gib-efatura' ); ?></button>
				</div>

				<?php if ( empty( $invoice['irsaliye_no'] ) ) : ?>
					<div class="wgf-irsaliye-add-box" style="display:none;margin-top:8px;">
						<p>
							<input type="text" class="widefat wgf-irsaliye-no" placeholder="<?php esc_attr_e( 'İrsaliye No', 'woo-gib-efatura' ); ?>" />
						</p>
						<p>
							<input type="text" class="widefat wgf-irsaliye-tarihi" placeholder="gg/aa/yyyy" />
						</p>
						<button type="button" class="button button-primary wgf-btn" data-action="add_irsaliye"><?php esc_html_e( 'İrsaliyeyi Kaydet', 'woo-gib-efatura' ); ?></button>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( ! empty( $invoice['dosya_yolu'] ) ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_download_invoice&invoice_id=' . $invoice['id'] ), 'wgf_download_' . $invoice['id'] ) ); ?>">
						<?php esc_html_e( 'Faturayı İndir', 'woo-gib-efatura' ); ?>
					</a>
				<?php endif; ?>
				<button type="button" class="button button-primary wgf-btn" data-action="send_email"><?php esc_html_e( 'Faturayı Gönder', 'woo-gib-efatura' ); ?></button>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<button type="button" class="button button-primary" id="wgf-toggle-form" data-irsaliye="0"><?php esc_html_e( 'Fatura Oluştur', 'woo-gib-efatura' ); ?></button>
		<button type="button" class="button" id="wgf-toggle-form-irsaliye" data-irsaliye="1"><?php esc_html_e( 'İrsaliyeli Fatura Oluştur', 'woo-gib-efatura' ); ?></button>

		<div id="wgf-create-form" style="display:none;margin-top:10px;">
			<p class="description"><?php esc_html_e( 'Aşağıdaki bilgiler siparişten otomatik dolduruldu, gerekirse düzenleyebilirsiniz.', 'woo-gib-efatura' ); ?></p>

			<p>
				<label><?php esc_html_e( 'Fatura Tarihi', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="faturaTarihi" value="<?php echo esc_attr( $defaults['faturaTarihi'] ); ?>" placeholder="gg/aa/yyyy" />
				<span class="description"><?php esc_html_e( 'Varsayılan bugündür. Siparişten farklı bir ayda fatura kesecekseniz (irsaliye önce, fatura sonra kesildiyse) burayı güncelleyin — kanunen irsaliye tarihinden en geç 7 gün sonrasına kadar fatura kesilmelidir.', 'woo-gib-efatura' ); ?></span>
			</p>
			<p>
				<label><?php esc_html_e( 'Fatura Tipi', 'woo-gib-efatura' ); ?></label><br />
				<span class="wgf-fatura-tipi-label"><?php echo 'kurumsal' === $defaults['faturaTipi'] ? esc_html__( 'Kurumsal (Unvan + VKN)', 'woo-gib-efatura' ) : esc_html__( 'Bireysel (Ad Soyad + TCKN)', 'woo-gib-efatura' ); ?></span>
			</p>
			<p>
				<label><?php esc_html_e( 'Ad', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="aliciAdi" value="<?php echo esc_attr( $defaults['aliciAdi'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Soyad', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="aliciSoyadi" value="<?php echo esc_attr( $defaults['aliciSoyadi'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Firma Unvanı (kurumsal için)', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="aliciUnvan" value="<?php echo esc_attr( $defaults['aliciUnvan'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'TC Kimlik / Vergi No', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="vknTckn" value="<?php echo esc_attr( $defaults['vknTckn'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Vergi Dairesi', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="vergiDairesi" value="<?php echo esc_attr( $defaults['vergiDairesi'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Adres', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="adres" value="<?php echo esc_attr( $defaults['adres'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Mahalle/Semt/İlçe', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="mahalleSemtIlce" value="<?php echo esc_attr( $defaults['mahalleSemtIlce'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Şehir', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="sehir" value="<?php echo esc_attr( $defaults['sehir'] ); ?>" />
			</p>
			<p>
				<label><?php esc_html_e( 'Ülke', 'woo-gib-efatura' ); ?></label>
				<input type="text" class="widefat" name="ulke" value="<?php echo esc_attr( $defaults['ulke'] ); ?>" />
			</p>

			<?php if ( $order->get_currency() !== 'TRY' ) : ?>
				<p>
					<label><?php echo esc_html( sprintf( __( 'Döviz Kuru (%s → TRY)', 'woo-gib-efatura' ), $order->get_currency() ) ); ?></label>
					<input type="number" step="0.0001" min="0" class="widefat" name="dovizKuru" value="" required />
				</p>
			<?php endif; ?>

			<div id="wgf-irsaliye-fields" style="display:none;">
				<p>
					<label><?php esc_html_e( 'İrsaliye No', 'woo-gib-efatura' ); ?></label>
					<input type="text" class="widefat" name="irsaliyeNumarasi" value="<?php echo esc_attr( $defaults['irsaliyeNumarasi'] ); ?>" />
				</p>
				<p>
					<label><?php esc_html_e( 'İrsaliye Tarihi', 'woo-gib-efatura' ); ?></label>
					<input type="text" class="widefat" name="irsaliyeTarihi" value="<?php echo esc_attr( $defaults['irsaliyeTarihi'] ); ?>" placeholder="gg/aa/yyyy" />
				</p>
			</div>

			<p>
				<label><?php esc_html_e( 'Açıklama / Not', 'woo-gib-efatura' ); ?></label>
				<textarea class="widefat" name="not" rows="2"><?php echo esc_textarea( $defaults['not'] ); ?></textarea>
			</p>

			<button type="button" class="button button-primary wgf-btn" data-action="create_invoice"><?php esc_html_e( 'Faturayı Oluştur', 'woo-gib-efatura' ); ?></button>
		</div>

	<?php endif; ?>

	<span class="spinner wgf-spinner"></span>
	<div class="wgf-message"></div>
</div>
