# Hostinger (ve diğer paylaşımlı hostingler) Kurulum Rehberi

BOZKURT; Hostinger, Natro, Turhost, Güzel Hosting, İHS, Radore, DorukNet gibi cPanel/Plesk/hPanel tabanlı tüm PHP hostinglerinde çalışır.

## 1. PHP sürümü

hPanel › **Web Siteleri › Yönet › Gelişmiş › PHP Yapılandırması** › **PHP 8.3** (en az 8.1).
Aynı ekranda **PHP Uzantıları** sekmesinde şunların işaretli olduğundan emin olun: `pdo_sqlite`, `pdo_mysql`, `mbstring`, `fileinfo`, `dom`, `gd`, `zip` (Hostinger'da varsayılan olarak açıktır).

Önerilen PHP seçenekleri: `upload_max_filesize = 32M`, `post_max_size = 40M`, `memory_limit = 256M`.

## 2. Dosyaları yükleme

**Dosya Yöneticisi ile:** `public_html` klasörünü açın › *Yükle* › `bozkurt-cms.zip` › sağ tık › *Ayıkla*. Dosyalar `public_html/bozkurt-cms/` içine çıktıysa hepsini bir üst klasöre taşıyın (`index.php` doğrudan `public_html` içinde olmalı).

**FTP ile:** FileZilla'da hPanel › *Dosyalar › FTP Hesapları* bilgileriyle bağlanın, klasör içeriğini `public_html/`'a sürükleyin. Gizli dosyaların (`.htaccess`) da yüklendiğinden emin olun (FileZilla › Sunucu › *Gizli dosyaları göstermeye zorla*).

## 3. Klasör izinleri

Genellikle gerekmez. Kurulum ekranı “yazılabilir değil” derse: `veri/` ve `yuklemeler/` klasörlerini **755** yapın (Dosya Yöneticisi › sağ tık › İzinler).

## 4. Veritabanı seçimi

- **SQLite (önerilen):** Hiçbir şey yapmayın. Veritabanı `veri/` klasöründe rastgele adlı bir dosyada tutulur ve web'den erişilemez. Küçük-orta siteler (on binlerce içerik, günde on binlerce ziyaret) için fazlasıyla yeterlidir.
- **MySQL:** hPanel › *Veritabanları › MySQL Veritabanları* › yeni veritabanı + kullanıcı oluşturun. Kurulum ekranında sunucu `localhost`, veritabanı adı `u123456789_bozkurt` biçimindedir.

## 5. Kurulum

`https://alanadiniz.com/yonetim/` adresine gidin, site adını, yönetici bilgilerinizi girin, veritabanını seçin. Kurulum bitince panele yönlendirilirsiniz.

## 6. Yayın öncesi kontrol listesi

- [ ] hPanel › **SSL** etkin; `.htaccess` içindeki HTTPS yönlendirme satırlarının yorumunu kaldırın.
- [ ] Panel › Profil › **İki adımlı doğrulama** açık.
- [ ] Panel › Ayarlar › E-posta: **SMTP** — Hostinger için `smtp.hostinger.com`, port `465`, SSL, kullanıcı: hPanel'de oluşturduğunuz e-posta adresi. “Test e-postası gönder” ile deneyin.
- [ ] Panel › Ayarlar › KVKK: aydınlatma metnini işletmenize göre güncelleyin (bir hukukçuya kontrol ettirin), form saklama süresini belirleyin.
- [ ] Panel › Ayarlar › SEO: Google Search Console ve Yandex Webmaster doğrulama kodları; `sitemap.xml` adresini her ikisine de gönderin.
- [ ] Panel › Ayarlar › “Arama motorlarını engelle” **kapalı**.
- [ ] Panel › Yedekleme: ilk tam yedeği indirin.

## 7. Alt klasöre kurulum

`alanadiniz.com/site/` gibi bir alt klasöre kurduysanız `.htaccess` içindeki `RewriteBase /` satırını `RewriteBase /site/` yapın. Diğer her şey otomatik algılanır.

## 8. Güzel URL çalışmıyorsa

Sunucunuz `.htaccess` desteklemiyorsa BOZKURT otomatik olarak `index.php?yol=blog` biçimine geçer. Elle değiştirmek için `veri/yapilandirma.php` içinde `'guzel_url' => true/false`.

## 9. Sık sorulanlar

**Beyaz sayfa / 500 hatası?** `veri/hatalar.log` dosyasına bakın. Geçici olarak `veri/yapilandirma.php` içinde `'hata_ayiklama' => true` yapın.

**“Çok fazla başarısız deneme”?** 15 dakika bekleyin. Acil durumda veritabanındaki `bz_giris_denemeleri` tablosunu boşaltın.

**Şifremi unuttum?** Giriş ekranındaki “Şifremi unuttum” bağlantısını kullanın (SMTP ayarlı olmalı). E-posta çalışmıyorsa phpMyAdmin ile `bz_kullanicilar.sifre` alanına PHP `password_hash('yeni-sifre', PASSWORD_DEFAULT)` çıktısını yazın.

**Cloudflare kullanıyorsanız** Ayarlar › Gelişmiş › “Ters vekil başlıklarına güven”i açın (yalnızca site gerçekten Cloudflare arkasındaysa).

**Yeniden kurulum** gerekiyorsa `veri/` içindeki `.sqlite`, `yapilandirma.php` ve `kurulum.kilit` dosyalarını silin (önce yedek alın).

**Taşıma (başka hostinge veya SQLite → MySQL)?** Panel › Yedekleme › Tam yedek (ZIP) indirin, yeni sunucuya temiz kurulum yapın, Yedekleme › Geri yükle.

**Cloudflare önbelleği?** `/yonetim/*`, `/api/*`, `/mcp` ve `/odeme/*` için önbelleği atlama kuralı ekleyin.
