# Mimari

BOZKURT CMS bağımlılıksız bir PHP 8.1+ uygulamasıdır. Tüm sınıflar `bozkurt/src/` altında `Bozkurt` ad alanındadır ve `bozkurt/boot.php` içindeki küçük otomatik yükleyiciyle yüklenir.

English: [en/architecture.md](en/architecture.md)

## Klasör yapısı

```
index.php            Ön yüz girişi → App::boot() → Site::run()
yonetim/index.php    Panel girişi → ilk çalıştırmada Installer, sonra Admin::run()
yonlendirici.php     `php -S` için yönlendirici (yalnızca geliştirme; web'den kapalı)
bozkurt/
  boot.php           BZ_VERSION, BZ_REPO, yol sabitleri, otomatik yükleyici
  src/               Çekirdek sınıflar + yardimcilar.php (genel yardımcı fonksiyonlar)
  gorunumler/        Panel görünümleri
  lang/              tr.php, en.php (sistem metinleri), panel-en.php, panel-en-js.php (panel çevirisi)
sablonlar/           Site şablonları (*.html), parcalar/, diller/*.json
tema/                Şablonların herkese açık varlıkları
yuklemeler/          Medya (YYYY/AA/…); betik çalıştırma kapalı
veri/                Çalışma verisi — web'den asla erişilmez
  yapilandirma.php   Kurulumun yazdığı yapılandırma
  bozkurt-*.sqlite   SQLite veritabanı
  onbellek/          derlenmis/ (derlenmiş şablonlar), sayfalar/ (sayfa önbelleği), hiz/ (API hız sınırı), sablonlar.json
  oturumlar/         PHP oturumları
  yedekler/          Her güncellemeden önce alınan otomatik yedek
testler/             Test paketleri + PHPStan önyüklemesi
bin/depo-ayarla.php  CLI: GitHub depo adını belgelerde/rozetlerde/güncelleyicide değiştirir
```

## Çekirdek sınıflar

| Sınıf | Görevi |
|---|---|
| `App` | Açılış, yapılandırma, ayar önbelleği, kök yol algılama, diller, veritabanı, şema yükseltme |
| `Db` | SQLite ve MySQL/MariaDB için ince PDO katmanı; tabloları oluşturur ve günceller |
| `Site` | Ön yüz yönlendiricisi: sistem rotaları, bakım modu, `.md` sürümler, sayfa önbelleği, şablon işleme, 404 + yönlendirmeler, yönetici çubuğu |
| `Template` | Şablonlardaki alan tanımlarını tarar, denetler ve `<bz:*>` etiketlerini PHP'ye derler |
| `Runtime` | Derlenmiş şablonu çalıştırır: değişkenler, süzgeçler, koşullar, listeler, formlar, SEO/JSON-LD |
| `Content` | İçerik modeli: şablonlar, kayıtlar, çeviriler, sürümler, genel alanlar, doğrulama, API görünümü |
| `Admin` | Panel denetleyicisi |
| `Auth`, `Totp` | Giriş, oturum, roller, kilit, TOTP 2FA |
| `Sanitizer`, `Markdown` | İzin listeli HTML temizleyici; Markdown → HTML |
| `Media` | Yükleme, MIME denetimi, küçültme, WebP, EXIF temizliği |
| `Forms`, `Mailer`, `Sms` | Formlar ve spam koruması; SMTP/`mail()`; Netgsm SMS |
| `Feeds` | sitemap.xml, robots.txt, RSS, llms.txt, Markdown sürümler |
| `Api`, `Mcp`, `Tokens`, `Webhook` | REST API, MCP sunucusu, API anahtarları, imzalı web kancaları |
| `Ai` | OpenAI uyumlu YZ çağrıları |
| `Payment` | PayTR ve iyzico ödeme ve bildirimleri, siparişler |
| `Redirects`, `Cache`, `Backup`, `Tools` | 301 + 404 günlüğü; sayfa önbelleği; yedek/geri yükleme; güncelleyici, WordPress içe aktarma, statik dışa aktarma |
| `Validate`, `Str`, `Lang` | TCKN/VKN/IBAN/il/tatil doğrulayıcıları; Türkçe metin araçları; çeviriler |
| `Installer`, `Sample` | Kurulum sihirbazı; örnek içerik |

## İstek akışı (ön yüz)

1. `App::boot()` yapılandırmayı, saat dilimini ve kök yolu yükler.
2. `Site::run()`: kurulu değilse `/yonetim/`'e yönlendirir → şema sürümünü denetler → güvenlik başlıklarını gönderir → kanonik adres (301) → dil önekini (`/en/`) ayıklar.
3. Sistem rotaları: `sitemap.xml`, `robots.txt`, `llms.txt`, `llms-full.txt`, `rss.xml`, `{sablon}/rss.xml`, `api/…`, `mcp`, `odeme/…`.
4. Bakım modu (ziyaretçiye 503) → `.md` sürümler → sayfa önbelleği (yalnızca anonim GET ve izinli sorgu parametreleri).
5. `resolve()`: şablon + isteğe bağlı slug → derlenmiş şablon → `Runtime` → HTML.
6. 404 ise önce yönlendirme kuralları, sonra 404 günlüğü ve 404 şablonu. Giriş yapılmışsa yönetici çubuğu eklenir, değilse sayfa önbelleğe yazılır.

## Şablon hattı

1. **Tarama** — `Template::scan()` alan tanımlarını çıkarır; sonuç `veri/onbellek/sablonlar.json`'da saklanır.
2. **Derleme** — `Template::compile()` şablondaki ham PHP'yi etkisizleştirir, etiketleri ve `{{ }}` ifadelerini `Runtime` çağrılarına çevirir; çıktı `veri/onbellek/derlenmis/` altında.
3. **İşleme** — derlenmiş dosya sayfa verisi, site ayarları ve genel alanlarla çalıştırılır.
4. **Denetim** — `Template::lint()` kapatılmamış/yanlış yerdeki etiketleri, bilinmeyen etiket ve alan türlerini, geçersiz alan adlarını bildirir.

## Veri modeli

`bz_icerik` (kayıtlar; alan değerleri JSON `veri` sütununda), `bz_surumler` (son 25 sürüm), `bz_genel`, `bz_ayarlar`, `bz_medya`, `bz_formlar`, `bz_kullanicilar`, `bz_giris_denemeleri`, `bz_gunluk`, `bz_yonlendirmeler`, `bz_404`, `bz_tokenlar` (SHA-256 özetli API anahtarları), `bz_sifre_sifirlama`, `bz_siparisler`.

Alan değerleri JSON tutulduğu için şablona alan eklemek göç gerektirmez. Yedekler bu tabloları veritabanından bağımsız JSON olarak dışa aktarır; SQLite ⇄ MySQL taşıma da bu yolla yapılır.
