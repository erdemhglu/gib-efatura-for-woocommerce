<?php
/**
 * @var array $s Mevcut ayarlar (WGF_Settings::all()).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wgf-settings">
	<h1><?php esc_html_e( 'GİB e-Fatura Ayarları', 'gib-efatura-for-woocommerce' ); ?></h1>

	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ayarlar kaydedildi.', 'gib-efatura-for-woocommerce' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wgf_save_settings' ); ?>
		<input type="hidden" name="action" value="wgf_save_settings" />

		<h2 class="title"><?php esc_html_e( 'API Bağlantısı', 'gib-efatura-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ortam', 'gib-efatura-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="test_mode" id="wgf_test_mode" value="1" <?php checked( $s['test_mode'] ); ?> />
						<?php esc_html_e( 'Test (GİB e-Arşiv Test Portalı) API kullan', 'gib-efatura-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Canlıya geçmeden önce test API üzerinde deneme yapmanız önerilir. Test faturaları resmi geçerlilik taşımaz ve SMS ile imzalanamaz.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_prod_username"><?php esc_html_e( 'Canlı Kullanıcı Kodu', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_prod_username" name="prod_username" value="<?php echo esc_attr( $s['prod_username'] ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_prod_password"><?php esc_html_e( 'Canlı Parola', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="wgf_prod_password" name="prod_password" value="" autocomplete="new-password" placeholder="<?php echo $s['prod_password'] ? esc_attr__( '•••••••• (değiştirmek için doldurun)', 'gib-efatura-for-woocommerce' ) : ''; ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_test_username"><?php esc_html_e( 'Test Kullanıcı Kodu', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_test_username" name="test_username" value="<?php echo esc_attr( $s['test_username'] ); ?>" autocomplete="off" />
					<button type="button" class="button" id="wgf_fetch_test_creds"><?php esc_html_e( 'Test Kullanıcısı Al', 'gib-efatura-for-woocommerce' ); ?></button>
					<p class="description"><?php esc_html_e( 'GİB test portalından otomatik bir test kullanıcı kodu talep eder ve parolasını (varsayılan "1") aşağıya yazar.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_test_password"><?php esc_html_e( 'Test Parola', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="wgf_test_password" name="test_password" value="" autocomplete="new-password" placeholder="<?php echo $s['test_password'] ? esc_attr__( '•••••••• (değiştirmek için doldurun)', 'gib-efatura-for-woocommerce' ) : ''; ?>" />
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Fatura Varsayılanları', 'gib-efatura-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wgf_default_note"><?php esc_html_e( 'Varsayılan Açıklama / Not', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="3" id="wgf_default_note" name="default_note"><?php echo esc_textarea( $s['default_note'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Kullanılabilir yer tutucular:', 'gib-efatura-for-woocommerce' ); ?>
						<code>{siparis_no}</code>, <code>{magaza_adi}</code>, <code>{tarih}</code>, <code>{musteri_adi}</code>, <code>{odeme_sekli}</code>, <code>{gonderim_sekli}</code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_default_country"><?php esc_html_e( 'Varsayılan Ülke', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td><input type="text" class="regular-text" id="wgf_default_country" name="default_country" value="<?php echo esc_attr( $s['default_country'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_default_district"><?php esc_html_e( 'Varsayılan İlçe/Semt', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_default_district" name="default_district" value="<?php echo esc_attr( $s['default_district'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Siparişte ilçe bilgisi bulunamazsa kullanılır.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_default_tax_id"><?php esc_html_e( 'Varsayılan TC Kimlik/Vergi No', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wgf_default_tax_id" name="default_tax_id" value="<?php echo esc_attr( $s['default_tax_id'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Müşteriden TC Kimlik/Vergi No alınamazsa GİB\'in nihai tüketici için kullanılmasına izin verdiği 11111111111 numarası kullanılır.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Otomatik İşlemler', 'gib-efatura-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="auto_email" value="1" <?php checked( $s['auto_email'] ); ?> />
						<?php esc_html_e( 'Fatura imzalandığında (veya test modunda oluşturulduğunda) müşteriye otomatik e-posta gönder', 'gib-efatura-for-woocommerce' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="customer_download" value="1" <?php checked( $s['customer_download'] ); ?> />
						<?php esc_html_e( 'Müşteri "Hesabım" sayfasından faturasını indirebilsin', 'gib-efatura-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Eklenti Kaldırıldığında', 'gib-efatura-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $s['delete_data_on_uninstall'] ); ?> />
						<?php esc_html_e( 'Eklenti WordPress üzerinden tamamen silinirse tüm fatura kayıtlarını, dosyalarını ve ayarları da sil', 'gib-efatura-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'İşaretli değilse, eklentiyi silseniz bile fatura kayıtlarınız veritabanında saklanmaya devam eder.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Alan Eşleştirme (Kurumsal Fatura)', 'gib-efatura-for-woocommerce' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Checkout sayfanızda TC Kimlik/Vergi No, Vergi Dairesi veya İlçe bilgisi toplayan başka bir alan/eklenti varsa, bu alanların meta anahtarlarını (virgülle ayırarak, öncelik sırasına göre) aşağıya yazın. Eklenti sipariş verisinde bu anahtarları sırayla arar; billing_company (firma unvanı) dolu ve vergi no 10 haneli ise fatura otomatik olarak "kurumsal" (VKN\'li) düzenlenir, aksi halde TC Kimlik No ile "bireysel" fatura düzenlenir.', 'gib-efatura-for-woocommerce' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wgf_map_tax_id"><?php esc_html_e( 'TC Kimlik / Vergi No alan anahtarları', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td><input type="text" class="large-text" id="wgf_map_tax_id" name="field_map_tax_id" value="<?php echo esc_attr( $s['field_map_tax_id'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_map_tax_office"><?php esc_html_e( 'Vergi Dairesi alan anahtarları', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td><input type="text" class="large-text" id="wgf_map_tax_office" name="field_map_tax_office" value="<?php echo esc_attr( $s['field_map_tax_office'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_map_district"><?php esc_html_e( 'İlçe/Semt alan anahtarları', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td><input type="text" class="large-text" id="wgf_map_district" name="field_map_district" value="<?php echo esc_attr( $s['field_map_district'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_map_city"><?php esc_html_e( 'Şehir alan anahtarları', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="wgf_map_city" name="field_map_city" value="<?php echo esc_attr( $s['field_map_city'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Checkout\'unuzda şehir bilgisini WooCommerce\'in "İl/State" alanı yerine ayrı bir özel alana/eklentiye yazıyorsa buraya ekleyin. Burada bulunamazsa sırasıyla City alanı, sonra State (il/eyalet, plaka koduysa yukarıdaki ayarla il adına çevrilir) denenir.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Adres Satırı Eşlemesi', 'gib-efatura-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="swap_address_lines" value="1" <?php checked( $s['swap_address_lines'] ); ?> />
						<?php esc_html_e( 'Adres Satırı 1\'i Mahalle/Semt, Adres Satırı 2\'yi Cadde/Sokak/Bina adresi olarak kullan', 'gib-efatura-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Checkout\'unuzda müşteriler mahalle bilgisini "Adres Satırı 1", detaylı adresi "Adres Satırı 2" alanına giriyorsa bunu işaretleyin (yukarıdaki İlçe/Semt alan anahtarları önce denenir, sadece boşsa bu sıralama kullanılır). İşaretli değilse standart WooCommerce sıralaması (Adres Satırı 1 = adres, Adres Satırı 2 = ek bilgi) kullanılır.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Şehir Plaka Kodu', 'gib-efatura-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="convert_plate_code_to_city" value="1" <?php checked( $s['convert_plate_code_to_city'] ); ?> />
						<?php esc_html_e( 'Şehir alanı (fatura/teslimat ili) "34" veya "TR34" gibi bir plaka koduysa, otomatik olarak il adına ("İstanbul") çevir', 'gib-efatura-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Bazı checkout eklentileri şehir bilgisini WooCommerce\'in "state" (il/eyalet) alanına plaka kodu olarak kaydeder. Bu işaretliyken eklenti, il adı bulunamadığında bu değeri kontrol eder ve plaka koduysa 81 ilin adına çevirir.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'KDV Dahil Fiyat Yazan Kalemler (Ödeme Altyapısı Ücretleri vb.)', 'gib-efatura-for-woocommerce' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Bazı ödeme altyapıları (taksit farkı, POS/kapıda ödeme ücreti vb.) siparişe KDV hesaplamadan, direkt KDV dahil tutarı yazan bir kalem ekler. Bu kalemlerin adını aşağıya (her satıra bir tane olacak şekilde, regex olarak) yazarsanız, fatura kesilirken bu kalemin tutarı KDV dahil kabul edilir; KDV hariç tutar geriye doğru hesaplanıp kalem "net tutar + %KDV" şeklinde faturaya yazılır. Örnek: kalem tutarı 69,53 TL ise ve oran %20 ise, faturada 57,94 + %20 KDV olarak görünür.', 'gib-efatura-for-woocommerce' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wgf_vat_included_item_patterns"><?php esc_html_e( 'Kalem Adı Regex Kalıpları', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<textarea class="large-text code" rows="4" id="wgf_vat_included_item_patterns" name="vat_included_item_patterns" placeholder="Taksit [Ff]ark[ıi]&#10;Kap[ıi]da [ÖO]deme.*[Üü]creti"><?php echo esc_textarea( $s['vat_included_item_patterns'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Her satır ayrı bir regex kalıbıdır (delimiter yazmayın, örn: Taksit Farkı). Kalem adı bu kalıplardan biriyle eşleşirse KDV dahil kabul edilir. Büyük/küçük harf duyarsızdır.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wgf_vat_included_rate"><?php esc_html_e( 'KDV Oranı (%)', 'gib-efatura-for-woocommerce' ); ?></label></th>
				<td>
					<input type="number" step="1" min="0" max="100" class="small-text" id="wgf_vat_included_rate" name="vat_included_rate" value="<?php echo esc_attr( $s['vat_included_rate'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Eşleşen kalemlerin KDV dahil tutarından KDV hariç tutarı geriye doğru hesaplamak için kullanılan oran.', 'gib-efatura-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Ayarları Kaydet', 'gib-efatura-for-woocommerce' ) ); ?>
	</form>

	<hr />

	<h2 class="title"><?php esc_html_e( 'Eklentiyi Sıfırla', 'gib-efatura-for-woocommerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Tüm ayarları varsayılana döndürür ve oluşturulmuş fatura kayıtlarını (GİB portalındaki kayıtlar hariç) siler. Siparişler üzerindeki fatura işaretleri kaldırılır, dilerseniz yeniden fatura oluşturabilirsiniz.', 'gib-efatura-for-woocommerce' ); ?></p>
	<button type="button" class="button button-secondary wgf-danger" id="wgf_reset_plugin"><?php esc_html_e( 'Eklentiyi Sıfırla', 'gib-efatura-for-woocommerce' ); ?></button>
	<span class="spinner" id="wgf_reset_spinner" style="float:none;"></span>
	<div id="wgf_reset_result"></div>
</div>
