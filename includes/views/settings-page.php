<?php
/**
 * @var array $s Mevcut ayarlar (WGF_Settings::all()).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wgf-settings">
	<h1><?php esc_html_e( 'GİB e-Fatura Ayarları', 'woo-gib-efatura' ); ?></h1>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi.', 'woo-gib-efatura' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wgf_save_settings' ); ?>
		<input type="hidden" name="action" value="wgf_save_settings" />

		<h2 class="title"><?php esc_html_e( 'API Bağlantısı', 'woo-gib-efatura' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ortam', 'woo-gib-efatura' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="test_mode" id="wgf_test_mode" value="1" <?php checked( $s['test_mode'] ); ?> />
						<?php esc_html_e( 'Test (GİB e-Arşiv Test Portalı) API kullan', 'woo-gib-efatura' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Canlıya geçmeden önce test API üzerinde deneme yapmanız önerilir. Test faturaları resmi geçerlilik taşımaz ve SMS ile imzalanamaz.', 'woo-gib-efatura' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_prod_username"><?php esc_html_e( 'Canlı Kullanıcı Kodu', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_prod_username" name="prod_username" value="<?php echo esc_attr( $s['prod_username'] ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_prod_password"><?php esc_html_e( 'Canlı Parola', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="wgf_prod_password" name="prod_password" value="" autocomplete="new-password" placeholder="<?php echo $s['prod_password'] ? esc_attr__( '•••••••• (değiştirmek için doldurun)', 'woo-gib-efatura' ) : ''; ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_test_username"><?php esc_html_e( 'Test Kullanıcı Kodu', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_test_username" name="test_username" value="<?php echo esc_attr( $s['test_username'] ); ?>" autocomplete="off" />
					<button type="button" class="button" id="wgf_fetch_test_creds"><?php esc_html_e( 'Test Kullanıcısı Al', 'woo-gib-efatura' ); ?></button>
					<p class="description"><?php esc_html_e( 'GİB test portalından otomatik bir test kullanıcı kodu talep eder ve parolasını (varsayılan "1") aşağıya yazar.', 'woo-gib-efatura' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_test_password"><?php esc_html_e( 'Test Parola', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="wgf_test_password" name="test_password" value="" autocomplete="new-password" placeholder="<?php echo $s['test_password'] ? esc_attr__( '•••••••• (değiştirmek için doldurun)', 'woo-gib-efatura' ) : ''; ?>" />
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Fatura Varsayılanları', 'woo-gib-efatura' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wgf_default_note"><?php esc_html_e( 'Varsayılan Açıklama / Not', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="wgf_default_note" name="default_note"><?php echo esc_textarea( $s['default_note'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Kullanılabilir yer tutucular:', 'woo-gib-efatura' ); ?>
						<code>{siparis_no}</code>, <code>{magaza_adi}</code>, <code>{tarih}</code>, <code>{musteri_adi}</code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_default_country"><?php esc_html_e( 'Varsayılan Ülke', 'woo-gib-efatura' ); ?></label></th>
				<td><input type="text" class="regular-text" id="wgf_default_country" name="default_country" value="<?php echo esc_attr( $s['default_country'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_default_district"><?php esc_html_e( 'Varsayılan İlçe/Semt', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_default_district" name="default_district" value="<?php echo esc_attr( $s['default_district'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Siparişte ilçe bilgisi bulunamazsa kullanılır.', 'woo-gib-efatura' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_default_tax_id"><?php esc_html_e( 'Varsayılan TC Kimlik/Vergi No', 'woo-gib-efatura' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_default_tax_id" name="default_tax_id" value="<?php echo esc_attr( $s['default_tax_id'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Müşteriden TC Kimlik/Vergi No alınamazsa GİB\'in nihai tüketici için kullanılmasına izin verdiği 11111111111 numarası kullanılır.', 'woo-gib-efatura' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Otomatik İşlemler', 'woo-gib-efatura' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="auto_email" value="1" <?php checked( $s['auto_email'] ); ?> />
						<?php esc_html_e( 'Fatura imzalandığında (veya test modunda oluşturulduğunda) müşteriye otomatik e-posta gönder', 'woo-gib-efatura' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="customer_download" value="1" <?php checked( $s['customer_download'] ); ?> />
						<?php esc_html_e( 'Müşteri "Hesabım" sayfasından faturasını indirebilsin', 'woo-gib-efatura' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Eklenti Kaldırıldığında', 'woo-gib-efatura' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $s['delete_data_on_uninstall'] ); ?> />
						<?php esc_html_e( 'Eklenti WordPress üzerinden tamamen silinirse tüm fatura kayıtlarını, dosyalarını ve ayarları da sil', 'woo-gib-efatura' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'İşaretli değilse, eklentiyi silseniz bile fatura kayıtlarınız veritabanında saklanmaya devam eder.', 'woo-gib-efatura' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Alan Eşleştirme (Kurumsal Fatura)', 'woo-gib-efatura' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Checkout sayfanızda TC Kimlik/Vergi No, Vergi Dairesi veya İlçe bilgisi toplayan başka bir alan/eklenti varsa, bu alanların meta anahtarlarını (virgülle ayırarak, öncelik sırasına göre) aşağıya yazın. Eklenti sipariş verisinde bu anahtarları sırayla arar; billing_company (firma unvanı) dolu ve vergi no 10 haneli ise fatura otomatik olarak "kurumsal" (VKN\'li) düzenlenir, aksi halde TC Kimlik No ile "bireysel" fatura düzenlenir.', 'woo-gib-efatura' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wgf_map_tax_id"><?php esc_html_e( 'TC Kimlik / Vergi No alan anahtarları', 'woo-gib-efatura' ); ?></label></th>
				<td><input type="text" class="large-text" id="wgf_map_tax_id" name="field_map_tax_id" value="<?php echo esc_attr( $s['field_map_tax_id'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_map_tax_office"><?php esc_html_e( 'Vergi Dairesi alan anahtarları', 'woo-gib-efatura' ); ?></label></th>
				<td><input type="text" class="large-text" id="wgf_map_tax_office" name="field_map_tax_office" value="<?php echo esc_attr( $s['field_map_tax_office'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_map_district"><?php esc_html_e( 'İlçe/Semt alan anahtarları', 'woo-gib-efatura' ); ?></label></th>
				<td><input type="text" class="large-text" id="wgf_map_district" name="field_map_district" value="<?php echo esc_attr( $s['field_map_district'] ); ?>" /></td>
			</tr>
		</table>

		<?php submit_button( __( 'Ayarları Kaydet', 'woo-gib-efatura' ) ); ?>
	</form>

	<hr />

	<h2 class="title"><?php esc_html_e( 'Eklentiyi Sıfırla', 'woo-gib-efatura' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Tüm ayarları varsayılana döndürür ve oluşturulmuş fatura kayıtlarını (GİB portalındaki kayıtlar hariç) siler. Siparişler üzerindeki fatura işaretleri kaldırılır, dilerseniz yeniden fatura oluşturabilirsiniz.', 'woo-gib-efatura' ); ?></p>
	<button type="button" class="button button-secondary wgf-danger" id="wgf_reset_plugin"><?php esc_html_e( 'Eklentiyi Sıfırla', 'woo-gib-efatura' ); ?></button>
	<span class="spinner" id="wgf_reset_spinner" style="float:none;"></span>
	<div id="wgf_reset_result"></div>
</div>
