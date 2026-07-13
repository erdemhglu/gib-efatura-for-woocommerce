# WooCommerce GİB e-Fatura

WooCommerce siparişlerinden [GİB e-Arşiv portalı](https://earsivportal.efatura.gov.tr) üzerinden e-Fatura/e-Arşiv fatura kesen, faturayı e-posta ile gönderen, sitede saklayan/indirtebilen ve mükerrer fatura oluşturmayı engelleyen bir WordPress eklentisi.

Bu eklenti, GİB portalı ile iletişim için [`mlevent/fatura`](https://github.com/mlevent/fatura) PHP kütüphanesini kullanır.

**Geliştirici:** Erdem Hacisalihoglu — [github.com/erdemhglu/woo-gib-efatura](https://github.com/erdemhglu/woo-gib-efatura)

## Özellikler

- Sipariş ekranından tek tıkla GİB e-Arşiv **taslak fatura** oluşturma (bireysel veya kurumsal/VKN'li).
- **İrsaliyeli fatura desteği**: "İrsaliyeli Fatura Oluştur" ile fatura kesilirken irsaliye no/tarihi referans olarak eklenebilir; irsaliye bilgisi unutulup taslak hâlde kaldıysa sonradan **"İrsaliye Oluştur"** ile taslağa eklenebilir (yalnızca imzalanmadan önce — bkz. Önemli Notlar).
- **Fatura Tarihi ayrı ve düzenlenebilir** bir alandır (varsayılan bugün); sipariş tarihinden bağımsız olarak, irsaliye önce kesilip fatura sonraki ay düzenlenecekse bu alan güncellenebilir.
- Canlı hesaplarda **SMS ile imzalama** akışı (taslağı resmi hâle getirir); test hesaplarında SMS doğrulaması GİB tarafından desteklenmediği için taslak aşamasında kalır.
- **Faturayı Görüntüle** — taslak ya da imzalanmış her fatura, GİB'in kendi belge görünümüyle yeni sekmede önizlenebilir.
- Faturayı **müşteriye e-posta ile gönderme** (WooCommerce e-posta sistemine entegre, konu/başlık WooCommerce > Ayarlar > E-postalar altında özelleştirilebilir).
- İmzalanan faturaların dosyasının **sitede saklanması** ve admin/müşteri tarafından **indirilmesi** (müşteri "Hesabım" sayfasından).
- **"GİB e-Fatura > Faturalar"** menüsünden tüm oluşturulan faturaların listelenmesi, filtrelenmesi, aranması.
- Fatura **açıklama/not** alanı için Ayarlar sayfasından varsayılan şablon (yer tutuculu).
- Aynı siparişe **ikinci kez fatura oluşturulması engellenir**, denenirse anlaşılır bir hata mesajı gösterilir.
- **Test API / Canlı API** arasında ayarlardan tek tıkla geçiş, test kullanıcısı otomatik alma.
- Checkout'ta farklı eklentilerle toplanan **TC Kimlik/Vergi No, Vergi Dairesi, İlçe** gibi alanları eşleştirebilme (kurumsal fatura desteği).
- Ayarlar sayfasından **eklentiyi sıfırlama** (ayarlar + fatura kayıtları, isteğe bağlı olarak dosyalar da silinir).

## Gereksinimler

- WordPress + WooCommerce (aktif ve güncel)
- PHP 8.1+
- Composer (kurulum sırasında bir kereliğine gerekir, sonrasında gerekmez)

## Kurulum

1. `woo-gib-efatura` klasörünü sunucunuzda `wp-content/plugins/` dizinine yükleyin (FTP/SFTP veya doğrudan sunucuda oluşturduysanız zaten oradadır).
2. Sunucuda **SSH ile** eklenti klasörüne girip bağımlılıkları yükleyin (bu adım **zorunludur**, aksi hâlde eklenti "gerekli PHP kütüphaneleri bulunamadı" uyarısı verir ve devre dışı kalır):

   ```bash
   cd wp-content/plugins/woo-gib-efatura

   # Composer kurulu değilse:
   curl -sS https://getcomposer.org/installer | php
   mv composer.phar /usr/local/bin/composer   # sudo gerekebilir

   composer install --no-dev
   ```

   Bu komut `mlevent/fatura` kütüphanesini ve bağımlılıklarını (`guzzlehttp/guzzle`, `ramsey/uuid`, `kwn/number-to-words`) `vendor/` klasörüne indirir. `vendor/` klasörü oluştuktan sonra eklenti kalıcı olarak kendi kendine yeterlidir, bir daha Composer'a ihtiyaç duyulmaz.

   Sunucuda hiç SSH/terminal erişiminiz yoksa: bu komutu kendi bilgisayarınızda (PHP 8.1+ ile) çalıştırıp oluşan `vendor/` klasörünü eklenti klasörünün içine FTP ile yükleyebilir veya [Releases](https://github.com/erdemhglu/woo-gib-efatura/releases) sayfasından eklentinin vendor dosyası eklenmiş halini indirebilirsiniz. Direkt kuruluma hazırdır.

3. WordPress admin panelinden **Eklentiler** sayfasından "WooCommerce GİB e-Fatura" eklentisini etkinleştirin.
4. **GİB e-Fatura > Ayarlar** sayfasından:
   - Önce "Test API kullan" işaretliyken **"Test Kullanıcısı Al"** ile bir test hesabı alıp deneme yapın (test parolası GİB'de her zaman `1`'dir).
   - Sorunsuz çalıştığını gördükten sonra "Test API kullan" kutucuğunu kaldırıp **canlı Kullanıcı Kodu/Parola** bilgilerinizi (GİB İnteraktif Vergi Dairesi'nden veya muhasebecinizden alınır) girin.
   - Canlıya geçmeden önce, gerçek GİB e-Arşiv hesabınıza (`earsivportal.efatura.gov.tr`) tarayıcıdan giriş yapıp profilde bir **cep telefonu numarası kayıtlı olduğundan** emin olun — SMS ile imzalama bu numaraya gönderilir.
   - Fatura açıklama şablonu, varsayılan ülke/ilçe/TC No ve alan eşleştirmelerini ihtiyacınıza göre düzenleyin.

## Kullanım

1. WooCommerce > Siparişler'den bir siparişi açın.
2. Sağ tarafta **"GİB e-Fatura"** kutusunda iki seçenek var:
   - **"Fatura Oluştur"** — normal, irsaliyesiz fatura.
   - **"İrsaliyeli Fatura Oluştur"** — mal kargoya çıkarken düzenlenmiş irsaliyenin no/tarihini de faturaya işler (kanunen standart sıra budur: önce irsaliye, en geç 7 gün içinde fatura — bkz. Önemli Notlar).
   Açılan formda bilgileri (gerekirse **Fatura Tarihi**'ni de) kontrol edip **"Faturayı Oluştur"** deyin.
3. Fatura taslak hâldeyken irsaliye bilgisini girmeyi unuttuysanız, henüz imzalanmadıysa **"İrsaliye Oluştur"** butonuyla sonradan ekleyebilirsiniz.
4. **"Faturayı Görüntüle (Önizleme)"** linkiyle, taslak dahil, faturanın GİB'deki görünümünü istediğiniz an kontrol edebilirsiniz.
5. Canlı modda: **"SMS ile İmzala"** butonuna basın, GİB hesabınıza kayıtlı telefona gelen kodu girip **"Doğrula"** deyin. Fatura resmi hâle gelir, dosyası otomatik indirilip saklanır ve (ayarlarda açıksa) müşteriye otomatik e-posta gider.
6. Test modda: fatura taslak olarak kalır (resmi geçerliliği yoktur), akışı test etmek için kullanılır; "Taslağı Sil" ile temizlenip tekrar denenebilir.
7. Tüm faturaları görmek için **GİB e-Fatura > Faturalar** menüsüne gidin.

## Önemli Notlar

- GİB e-Arşiv'de **taslak oluşturma** ile **imzalama** iki ayrı adımdır; bir fatura yalnızca imzalandıktan sonra resmi/yasal geçerlilik kazanır.
- SMS ile imzalama **test hesaplarında çalışmaz** (GİB kısıtlaması); bu nedenle test modunda oluşturulan faturalar taslak olarak kalır ve sonsuza dek imzalanamaz — bu beklenen bir durumdur.
- İmzalanmış (resmi) bir fatura, eklenti üzerinden **silinemez ve değiştirilemez** — irsaliye bilgisi eklemek dahil hiçbir alanı güncellenemez. Mevzuat gereği düzeltme ancak GİB portalından "İptal Talebi" veya bir iade faturası ile yapılabilir. Bu akış şu an eklentiye entegre değildir, GİB portalından elle yürütülmelidir.
- **İrsaliye - fatura sırası:** VUK Madde 231 gereği standart akış, mal sevk edilirken irsaliye düzenlenmesi ve faturanın irsaliye tarihinden **en geç 7 gün içinde** kesilmesidir. Eklenti irsaliyeyi kendisi oluşturmaz (bu ayrı bir süreçtir — matbu irsaliye defteri veya varsa e-İrsaliye sisteminiz); yalnızca zaten var olan irsaliyenin no/tarih bilgisini faturaya referans olarak işler.
- "İrsaliye Oluştur" (taslağa sonradan ekleme) yalnızca **taslak** durumundaki faturalarda çalışır; imzalanmış bir faturada bu seçenek hiç görünmez, çünkü GİB imzalı belgede değişikliğe izin vermez.
- Fatura dosyaları `wp-content/uploads/wgf-faturalar/` klasöründe saklanır; bu klasör `.htaccess` ile doğrudan tarayıcı erişimine kapatılmıştır, indirme yalnızca eklenti üzerinden (yetki/nonce kontrolü ile) yapılabilir.
- Fatura önizleme sayfası, GİB'den gelen belgeyi script çalıştırmayı tamamen engelleyen bir Content-Security-Policy ile gösterir (ek bir güvenlik önlemi).
- Bu eklenti resmi/mali veri oluşturur. Kullanımından doğabilecek sorumluluk kullanıcıya aittir; canlıya geçmeden önce test ortamında kapsamlı test yapmanız önerilir.

## Sorun Giderme

**"gerekli PHP kütüphaneleri bulunamadı" uyarısı** → `vendor/` klasörü eksik ya da eksik kurulmuş. Yukarıdaki "Kurulum" adım 2'yi tekrar çalıştırın, `composer install --no-dev` çıktısında hata olup olmadığına bakın.

**Composer "could not be resolved" / versiyon hatası** → `composer.json` içindeki `mlevent/fatura` sürüm kısıtlamasının Packagist'teki gerçek sürümlerle (şu an `^0.3.3`) uyumlu olduğundan emin olun.

**Yeni sürümde eklenen alanlar (ör. irsaliye) görünmüyor / "Unknown column" hatası** → Eklenti dosyaları güncellendiğinde veritabanı tablosu `WGF_DB_VERSION` numarası değiştiğinde otomatik güncellenir (bir sonraki sayfa yüklemesinde). Görünmüyorsa eklentiyi WordPress admin panelinden bir kez devre dışı bırakıp tekrar etkinleştirmeyi deneyin; bu `WGF_Install::create_table()`'ı yeniden tetikler.

**Beyaz sayfa / "Bu sitede ciddi bir sorun çıktı"** → Gerçek hatayı görmek için:
```bash
tail -n 60 wp-content/uploads/wc-logs/fatal-errors-*.log
```
veya `wp-content/debug.log` (WP_DEBUG_LOG açıksa). Fatal error mesajını paylaşarak destek isteyebilirsiniz.

**Bir buton tıklanınca hiçbir şey olmuyor** → Sayfayı sert yenileyin (Ctrl+F5). Tarayıcıda F12 > Console'da kırmızı bir JS hatası olup olmadığına bakın; genelde `admin.js` dosyasının o sayfada yüklenmemesinden kaynaklanır.

## Dosya Yapısı

```
woo-gib-efatura/
├── woo-gib-efatura.php        Ana eklenti dosyası
├── composer.json              mlevent/fatura bağımlılığı
├── uninstall.php               Eklenti tamamen silindiğinde veri temizliği
├── includes/                   PHP sınıfları
│   └── views/                  Admin ekran şablonları
├── templates/emails/           WooCommerce e-posta şablonları
└── assets/                     JS/CSS
```
