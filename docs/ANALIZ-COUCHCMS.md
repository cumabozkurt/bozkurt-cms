# CouchCMS — Uçtan Uca Analiz

> İncelenen kaynak: `github.com/CouchCMS/CouchCMS` (ana dal, Ekim 2026 itibarıyla). Depo ~940 dosya, çekirdek PHP ~27.000 satır (`tags.php` 8.005, `functions.php` 4.890, `page.php` 2.032, `field.php` 1.774, `folder.php` 1.606 satır). GitHub'da ~380 yıldız.

## 1. Felsefe ve güçlü yanlar

CouchCMS'in vaadi: *“PHP bilmeden, elinizdeki HTML'i dakikalar içinde CMS'e çevirin.”* Tasarımcı bir sayfanın en üstüne `<?php require_once('couch/cms.php'); ?>` ekler, düzenlenecek yerleri `<cms:editable>` ile sarar, en alta `<?php COUCH::invoke(); ?>` koyar ve sayfayı yönetici olarak bir kez ziyaret eder. Bu yaklaşımın gerçek değerleri:

1. **Sıfır öğrenme eğrisi** — WordPress tema hiyerarşisi, döngü, hook bilmek gerekmez.
2. **Tasarımın sahibi tasarımcıdır** — CMS, HTML'i dikte etmez; mevcut tasarım bozulmaz.
3. **Klonlanabilir sayfalar** (`clonable='1'`) — bir şablondan blog, portföy, etkinlik üretmek.
4. **Zengin etiket kütüphanesi** — 186 etiket fonksiyonu: `pages`, `folders`, `search`, `form`, `repeatable`, `related_pages`, `paypal`, `calendar`, `nested_pages`…
5. **Küçük ve kendi kendine yeten** — paylaşımlı hostingde çalışır.
6. **Eklentiler (addons)** — `repeatable`, `relation`, `routes`, `cart`, `inline`, `mosaic`, `page-builder`, `data-bound-form`, `extended-users`.

## 2. Mimari

| Katman | Dosyalar | Gözlem |
|---|---|---|
| Önyükleme | `cms.php`, `header.php`, `config.php` | Sabitlerle (`K_*`) yapılandırma; 60+ sabit elle düzenlenir |
| Veritabanı | `db.php`, `includes/mysql2i/` | `mysql_*` çağrıları; PHP 7+ için `mysql2i` uyumluluk katmanı ile `mysqli`'ye köprüleniyor. Yalnızca MySQL |
| Ayrıştırıcı | `parser/parser.php`, `HTMLParser.php` | Şablon **her istekte** ayrıştırılıp düğüm ağacına çevriliyor (önbellek kapalıyken maliyetli) |
| Etiketler | `tags.php` (8.000 satır tek sınıf) | Tüm etiketler tek dev sınıfta; test edilebilirlik düşük |
| Sayfa / alan | `page.php`, `field.php`, `folder.php` | Alanlar `couch_fields`, değerler `couch_data_text` / `couch_data_numeric` (EAV modeli) |
| Yönetim | `edit-pages.php`, `theme/_system/` | Sunucu tarafı render + jQuery; tema geçersiz kılma sistemi (snippets) |
| Medya | `includes/kcfinder/`, `includes/timthumb.php` | Üçüncü taraf dosya yöneticisi ve timthumb |
| Editör | `includes/ckeditor/`, `addons/nicedit/` | CKEditor 4 (2023'te ömrünü tamamladı) |
| Kimlik | `auth/PasswordHash.php` (phpass) | MD5 tabanlı taşınabilir hash yedeği içeriyor |
| Spam | `includes/securimage/`, `addons/recaptcha/` | Görsel captcha |

**Veri modeli (EAV):** her alan değeri ayrı satırdır. Esnektir, ancak listeleme ve süzme sorgularında çok sayıda JOIN gerekir; büyük sitelerde yavaşlar.

## 3. Zayıf yanlar ve teknik borç

1. **Eskimiş PHP tabanı** — `mysql_*` API'si PHP 7'de kaldırıldı; çekirdek hâlâ bu API ile yazılmış, bir köprü katmanıyla ayakta. `strict_types`, tip bildirimi, isim alanı (namespace), otomatik yükleme yok.
2. **Güvenlik geçmişi** — CHANGELOG'da 2015 tarihli güvenlik düzeltmeleri (Full Disclosure listesinde yayımlanmış iki açık). `timthumb.php` tarihsel olarak çok sayıda uzaktan kod çalıştırma açığıyla bilinir. phpass'in MD5 yedeği bugünün standartlarının altında. 2FA yok, kaba kuvvet kilidi yok.
3. **Bakım** — CHANGELOG'daki son başlık “2.0 - (2016/m/d)” olarak tamamlanmamış duruyor; geliştirme topluluk forumu üzerinden ve yavaş ilerliyor.
4. **Kurulum sürtünmesi** — `config.php` elle düzenleniyor; her şablonun üstüne/altına PHP satırı eklemek ve “yönetici olarak ziyaret et” adımı yeni başlayanları şaşırtıyor.
5. **Performans** — Her istekte HTML ayrıştırma + EAV sorguları; önbellek varsayılan kapalı.
6. **Modern ön yüz beklentileri** — WebP/AVIF, `srcset`, lazy-load, JSON-LD, Open Graph, sitemap varsayılan değil; çoğu elle kodlanıyor.
7. **Yönetim arayüzü** — 2016 tasarımı; koyu tema, mobil öncelik, klavye kısayolları, sürükle-bırak yükleme, SEO önizleme yok.
8. **Dil** — DE, EN, ES, FR, NL dil dosyaları var; **Türkçe yok**. Slug üretimi Türkçe karakterleri (ı, ğ, ş) doğru işlemiyor.
9. **Mevzuat** — KVKK/GDPR araçları (çerez onayı, açık rıza, veri saklama süresi, IP anonimleştirme) yok.
10. **Lisans** — CPAL 1.0: kaynak kodundaki atıf **hiçbir koşulda kaldırılamaz**; beyaz etiket için ücretli lisans gerekir.
11. **Headless/API** — İçeriği JSON olarak sunan yerleşik bir API yok.
12. **Test ve CI** — Depoda otomatik test ya da sürekli entegrasyon yok.

## 4. BOZKURT'un aldığı kararlar

| CouchCMS sorunu | BOZKURT çözümü |
|---|---|
| Her sayfaya PHP satırı + “yönetici olarak ziyaret et” | Ön yüz denetleyicisi (`index.php`) ve **otomatik şablon tarama**: dosyayı `sablonlar/`'a koymak yeter |
| Her istekte ayrıştırma | Şablonlar **PHP'ye derlenir** (OPcache), üstüne tam sayfa önbellek |
| `mysql_*` + yalnızca MySQL | PDO; **SQLite varsayılan** (sıfır ayar), MySQL/MariaDB isteğe bağlı |
| EAV ile ağır sorgular | Kayıt başına tek satır + JSON alan verisi; sistem sütunlarında indeksli sorgu |
| `config.php` düzenleme | Web kurulum sihirbazı; ayarlar panelden |
| CKEditor 4 + KCFinder + timthumb | Bağımlılıksız editör, yerleşik medya kütüphanesi, GD ile güvenli küçük resim; sunucu tarafı izin listesi HTML temizleyici |
| phpass, captcha | `password_hash` (bcrypt/argon), TOTP 2FA, giriş kilidi, CSRF, oturumsuz bal küpü + imzalı belirteç |
| Türkçe yok | Türkçe varsayılan; Türkçe slug, İ/ı duyarlı büyük/küçük harf, Türkçe tarih, ₺, telefon biçimi |
| KVKK yok | Çerez bandı, açık rıza kutusu, IP anonimleştirme, saklama süresi, silme, aydınlatma metni şablonu |
| SEO elle | `<bz:seo>` tek etiketle title/meta/OG/canonical/JSON-LD, sitemap, robots, RSS |
| API yok | Salt okunur JSON API |
| CPAL | MIT |

## 5. Kasıtlı olarak korunanlar

- **Tasarımcı önce** felsefesi ve HTML içinde etiket yaklaşımı (`<bz:alan>` ≈ `<cms:editable>`).
- Tekil/çoklu sayfa ayrımı (`coklu="evet"` ≈ `clonable='1'`), liste + detay görünümü (`gorunum` ≈ `k_is_list`/`k_is_page`).
- Tekrarlanan alanlar (Couch'ta eklenti, BOZKURT'ta çekirdek), ilişki alanları, formlar, arama, RSS, özel 404.
- Paylaşımlı hostingde FTP ile yükle-çalıştır sadeliği.
