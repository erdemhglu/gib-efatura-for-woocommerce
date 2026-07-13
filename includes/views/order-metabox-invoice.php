<?php
/**
 * Tek bir fatura (asıl satış faturası ya da bir iade faturası) için durum/aksiyon bloğu.
 * order-metabox.php içinden, asıl fatura ve her iade faturası için ayrı ayrı include edilir.
 *
 * @var array $invoice   Fatura kaydı (wgf_invoices satırı).
 * @var bool  $is_source Bu blok asıl satış faturası için mi render ediliyor (irsaliye/iade oluşturma
 *                       yalnızca asıl faturada gösterilir; bir iade faturasının tekrar iadesi desteklenmez).
 */
defined( 'ABSPATH' ) || exit;

$is_signed = WGF_Invoice_Repository::STATUS_SIGNED === $invoice['durum'];
$is_return = 'iade' === ( $invoice['belge_turu'] ?? 'satis' );
?>
<div class="wgf-invoice-block" data-invoice-id="<?php echo esc_attr( $invoice['id'] ); ?>">

	<?php if ( $is_signed ) : ?>
		<p>
			<span class="wgf-badge wgf-badge-imzalandi"><?php esc_html_e( 'İmzalandı (Resmi)', 'gib-efatura-for-woocommerce' ); ?></span>
			<?php if ( $is_return ) : ?>
				<span class="wgf-badge wgf-badge-iade"><?php esc_html_e( 'İade Faturası', 'gib-efatura-for-woocommerce' ); ?></span>
			<?php endif; ?>
		</p>
		<p><strong><?php esc_html_e( 'Belge No:', 'gib-efatura-for-woocommerce' ); ?></strong> <?php echo esc_html( $invoice['belge_no'] ?: '-' ); ?></p>
	<?php else : ?>
		<p>
			<span class="wgf-badge wgf-badge-taslak"><?php esc_html_e( 'Taslak (İmzalanmadı)', 'gib-efatura-for-woocommerce' ); ?></span>
			<?php if ( $is_return ) : ?>
				<span class="wgf-badge wgf-badge-iade"><?php esc_html_e( 'İade Faturası', 'gib-efatura-for-woocommerce' ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( 'test' === $invoice['mode'] ) : ?>
			<p class="description"><?php esc_html_e( 'Test API ile oluşturuldu. Test hesaplarında SMS ile imzalama yapılamaz, bu nedenle resmi geçerliliği yoktur.', 'gib-efatura-for-woocommerce' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<p>
		<strong><?php esc_html_e( 'Ortam:', 'gib-efatura-for-woocommerce' ); ?></strong>
		<?php echo esc_html( 'test' === $invoice['mode'] ? __( 'Test', 'gib-efatura-for-woocommerce' ) : __( 'Canlı', 'gib-efatura-for-woocommerce' ) ); ?><br />
		<strong><?php esc_html_e( 'Tutar:', 'gib-efatura-for-woocommerce' ); ?></strong>
		<?php echo esc_html( number_format_i18n( (float) $invoice['tutar'], 2 ) . ' ' . $invoice['para_birimi'] ); ?><br />
		<strong><?php esc_html_e( 'Alıcı:', 'gib-efatura-for-woocommerce' ); ?></strong> <?php echo esc_html( $invoice['alici_ad'] ); ?><br />
		<?php if ( $is_source ) : ?>
			<strong><?php esc_html_e( 'İrsaliye:', 'gib-efatura-for-woocommerce' ); ?></strong>
			<?php echo $invoice['irsaliye_no'] ? esc_html( $invoice['irsaliye_no'] . ( $invoice['irsaliye_tarihi'] ? ' (' . $invoice['irsaliye_tarihi'] . ')' : '' ) ) : esc_html__( 'yok', 'gib-efatura-for-woocommerce' ); ?><br />
		<?php endif; ?>
		<strong>UUID:</strong> <code style="word-break:break-all;font-size:10px;"><?php echo esc_html( $invoice['uuid'] ); ?></code>
	</p>

	<p>
		<a class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_preview_invoice&invoice_id=' . $invoice['id'] ), 'wgf_preview_' . $invoice['id'] ) ); ?>">
			<?php esc_html_e( 'Faturayı Görüntüle (Önizleme)', 'gib-efatura-for-woocommerce' ); ?>
		</a>
	</p>

	<div class="wgf-actions" data-invoice-id="<?php echo esc_attr( $invoice['id'] ); ?>">
		<?php if ( ! $is_signed ) : ?>
			<?php if ( 'canli' === $invoice['mode'] ) : ?>
				<button type="button" class="button button-primary wgf-btn" data-action="start_sms"><?php esc_html_e( 'SMS ile İmzala', 'gib-efatura-for-woocommerce' ); ?></button>
			<?php endif; ?>
			<?php if ( $is_source && empty( $invoice['irsaliye_no'] ) ) : ?>
				<button type="button" class="button" id="wgf-toggle-irsaliye-add"><?php esc_html_e( 'İrsaliye Oluştur', 'gib-efatura-for-woocommerce' ); ?></button>
			<?php endif; ?>
			<button type="button" class="button wgf-btn" data-action="delete_draft"><?php esc_html_e( 'Taslağı Sil', 'gib-efatura-for-woocommerce' ); ?></button>

			<div class="wgf-sms-box" style="display:none;margin-top:8px;">
				<input type="text" class="small-text wgf-sms-code" placeholder="<?php esc_attr_e( 'SMS Kodu', 'gib-efatura-for-woocommerce' ); ?>" />
				<button type="button" class="button button-primary wgf-btn" data-action="complete_sms"><?php esc_html_e( 'Doğrula', 'gib-efatura-for-woocommerce' ); ?></button>
			</div>

			<?php if ( $is_source && empty( $invoice['irsaliye_no'] ) ) : ?>
				<div class="wgf-irsaliye-add-box" style="display:none;margin-top:8px;">
					<p>
						<input type="text" class="widefat wgf-irsaliye-no" placeholder="<?php esc_attr_e( 'İrsaliye No', 'gib-efatura-for-woocommerce' ); ?>" />
					</p>
					<p>
						<input type="text" class="widefat wgf-irsaliye-tarihi" placeholder="gg/aa/yyyy" />
					</p>
					<button type="button" class="button button-primary wgf-btn" data-action="add_irsaliye"><?php esc_html_e( 'İrsaliyeyi Kaydet', 'gib-efatura-for-woocommerce' ); ?></button>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<?php if ( ! empty( $invoice['dosya_yolu'] ) ) : ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wgf_download_invoice&invoice_id=' . $invoice['id'] ), 'wgf_download_' . $invoice['id'] ) ); ?>">
					<?php esc_html_e( 'Faturayı İndir', 'gib-efatura-for-woocommerce' ); ?>
				</a>
			<?php endif; ?>
			<button type="button" class="button button-primary wgf-btn" data-action="send_email"><?php esc_html_e( 'Faturayı Gönder', 'gib-efatura-for-woocommerce' ); ?></button>

			<?php if ( empty( $invoice['iptal_talep_tarihi'] ) ) : ?>
				<button type="button" class="button wgf-btn-toggle" data-toggle="wgf-iptal-box"><?php esc_html_e( 'İptal Başvurusu Yap', 'gib-efatura-for-woocommerce' ); ?></button>
			<?php endif; ?>

			<?php if ( $is_source ) : ?>
				<button type="button" class="button wgf-btn-toggle" data-toggle="wgf-iade-box"><?php esc_html_e( 'İade Faturası Oluştur', 'gib-efatura-for-woocommerce' ); ?></button>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $is_signed ) : ?>
		<?php if ( ! empty( $invoice['iptal_talep_tarihi'] ) ) : ?>
			<p class="wgf-iptal-info">
				<strong><?php esc_html_e( 'İptal Başvurusu Gönderildi:', 'gib-efatura-for-woocommerce' ); ?></strong>
				<?php echo esc_html( mysql2date( 'd.m.Y H:i', $invoice['iptal_talep_tarihi'] ) ); ?><br />
				<em><?php echo esc_html( $invoice['iptal_aciklama'] ); ?></em>
			</p>
		<?php else : ?>
			<div class="wgf-iptal-box" style="display:none;margin-top:8px;">
				<p class="description"><?php esc_html_e( 'Başvuru GİB portalına gönderilir; alıcı yasal süre içinde itiraz etmezse kendiliğinden onaylanır.', 'gib-efatura-for-woocommerce' ); ?></p>
				<p>
					<textarea class="widefat wgf-iptal-aciklama" rows="2" placeholder="<?php esc_attr_e( 'İptal gerekçesi', 'gib-efatura-for-woocommerce' ); ?>"></textarea>
				</p>
				<button type="button" class="button button-primary wgf-btn" data-action="cancellation_request"><?php esc_html_e( 'Başvuruyu Gönder', 'gib-efatura-for-woocommerce' ); ?></button>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $is_source && $is_signed && ! empty( $returnable ) ) : ?>
		<div class="wgf-iade-box" style="display:none;margin-top:8px;border-top:1px solid #dcdcde;padding-top:8px;">
			<p>
				<label><?php esc_html_e( 'İade Fatura Tarihi', 'gib-efatura-for-woocommerce' ); ?></label>
				<input type="text" class="widefat wgf-iade-tarih" value="<?php echo esc_attr( current_time( 'd/m/Y' ) ); ?>" placeholder="gg/aa/yyyy" />
			</p>
			<table class="wgf-iade-items widefat" cellpadding="4">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Kalem', 'gib-efatura-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Sipariş Miktarı', 'gib-efatura-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'İade Miktarı', 'gib-efatura-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $returnable as $item_id => $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['name'] ); ?></td>
							<td><?php echo esc_html( rtrim( rtrim( sprintf( '%.4f', $item['qty'] ), '0' ), '.' ) ); ?></td>
							<td>
								<input type="number" class="small-text wgf-iade-qty" min="0" max="<?php echo esc_attr( $item['qty'] ); ?>" step="any" value="0" data-item-id="<?php echo esc_attr( $item_id ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label><?php esc_html_e( 'Açıklama / Not', 'gib-efatura-for-woocommerce' ); ?></label>
				<textarea class="widefat wgf-iade-not" rows="2"></textarea>
			</p>
			<button type="button" class="button button-primary wgf-btn" data-action="create_return_invoice"><?php esc_html_e( 'İade Faturasını Oluştur', 'gib-efatura-for-woocommerce' ); ?></button>
		</div>
	<?php endif; ?>

</div>
