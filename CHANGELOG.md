# Değişiklik Günlüğü / Changelog

Bu proje [Anlamsal Sürümleme](https://semver.org/lang/tr/) kullanır. Biçim [Keep a Changelog](https://keepachangelog.com/tr-TR/1.1.0/) esinlidir.
This project follows [Semantic Versioning](https://semver.org/); the format is inspired by [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## 1.0.0 — 2026-10-08
İlk kararlı sürüm. / First stable release.

**Çekirdek**
- Derlenen şablon dili (`<bz:*>` etiketleri, `{{ }}` değişkenler, 29 süzgeç), şablon denetleyici
- 26 alan türü: tekrarlanan gruplar, blok düzenleyici, ilişki, Markdown, il, TCKN, VKN, IBAN…
- Tekil ve çoklu içerik; taslak, zamanlanmış yayın, önizleme, sürüm geçmişi, kopyalama
- Web kurulum sihirbazı (kurulum kilidiyle); SQLite veya MySQL; şema sürümleme ve otomatik yükseltme

**Çok dilli içerik**
- `/en/`, `/de/`… önekleri, çeviri iş akışı, hreflang + x-default, dil seçici, dil başına genel alanlar, şablon sözlükleri, RTL

**Editör deneyimi**
- Medya kütüphanesi: sürükle-bırak, otomatik küçültme, WebP, EXIF temizliği, alt metin
- Ön yüzde satır içi düzenleme, içerik takvimi, yerel otomatik taslak, kelime sayacı, canlı Türkçe SEO analizi
- Panel Türkçe ve İngilizce (kullanıcı başına), beyaz etiket, yayına hazırlık listesi

**Yapay zekâ**
- Editör yardımcısı (yazım, özet, başlık, serbest komut), SEO metni, toplu çeviri, görselden alt metin — OpenAI uyumlu her sağlayıcı ve yerel Ollama
- llms.txt, llms-full.txt, her sayfanın `.md` sürümü, robots.txt YZ bot politikası
- MCP sunucusu (Claude, ChatGPT, Cursor)

**SEO ve pazarlama**
- Meta/OG/canonical/JSON-LD (Article, Breadcrumb, FAQPage, LocalBusiness), görselli ve hreflang'li sitemap, RSS
- Süzülmüş listelerde ayrı başlık ve canonical, tarih arşivlerinde noindex; çevrilmemiş sayfalar hreflang/sitemap dışında ve noindex
- 301 yönlendirme yöneticisi, slug değişince otomatik 301, 404 günlüğü, kanonik adres düzeltme, SEO denetimi
- GA4, Meta Pixel, Yandex Metrica (onay sonrası), form dönüşüm olayları, UTM yakalama

**Türkiye paketi**
- KVKK: çerez bandı, açık rıza, IP anonimleştirme, saklama süresi; İYS onayı
- PayTR ve iyzico ödeme, siparişler, e-Fatura CSV; Netgsm SMS; resmî tatil takvimi

**Entegrasyon ve araçlar**
- REST API (okuma/yazma, kapsamlı anahtarlar, hız sınırı), imzalı web kancaları
- WordPress içe aktarıcı, statik site dışa aktarma, SHA-256 doğrulamalı tek tıkla güncelleme, DB'den bağımsız yedek

**Güvenlik**
- Roller, TOTP 2FA (tekrar oynatma korumalı), IP + hesap bazlı kilit, e-posta ile şifre sıfırlama, oturum süreleri
- CSP/HSTS, CSRF, izin listeli HTML temizleyici, yükleme taraması, SSRF ve DNS rebinding koruması (bkz. docs/GUVENLIK-DENETIMI.md)

**Kalite**
- `testler/kapsamli.php` uçtan uca + saldırı testleri (SQLite ve MySQL), `testler/tarama.php` site/panel taraması, `testler/duman.php`, PHPStan seviye 5, PHP 8.1–8.4 CI

### Yayın öncesi düzeltmeler / Pre-release fixes

**Düzeltmeler**
- KVKK açık rıza kutusu bozuk HTML üretiyordu (bağlantı metne `…/kvkkAydınlatma Metni</a>` olarak sızıyordu); iletişim ve ödeme formları dahil tüm formlarda düzeltildi (`Runtime::tag_kvkk_onay`).
- **Güvenlik:** JSON-LD çıktısında `</script>` kaçışı mümkündü (içerik başlığı, SSS veya işletme bilgisinden kalıcı XSS). JSON-LD artık `<`, `>`, `&` kaçışlı üretiliyor (`Runtime::jsonLd`).
- **Güvenlik:** HTML temizleyicide kontrol karakteriyle başlayan `javascript:` bağlantıları (ör. `\x01javascript:`) canlı bağlantıya dönüşebiliyordu; şema denetiminden önce kontrol karakterleri ve boşluklar atılıyor (`Sanitizer::safeUrl`).
- **Güvenlik:** Panelde yapıştırılan HTML ve SEO analizindeki içerik canlı belgede `innerHTML` ile ayrıştırılıyordu (`<img onerror>` çalışabiliyordu); artık betik çalıştırmayan `DOMParser` kullanılıyor (`yonetim/assets/yonetim.js`).
- **Güvenlik:** SMTP'de STARTTLS başarısız olursa kimlik bilgileri düz metin gönderilebiliyordu; artık bağlantı kesilip hata veriliyor. Varsayılan gönderen alanı `Host` başlığı yerine site adresinden alınıyor (`Mailer`).
- **Güvenlik:** Web kancasında DNS çözümlemesi ile bağlantı arasındaki zaman farkı (DNS rebinding) kapatıldı; sabitlenen IP de genel adres olmalı (`Webhook`).
- Yazarlar ön yüzde satır içi düzenlemeyle yayın onayını atlayabiliyordu; artık paneldeki kural uygulanıyor (`Admin`).
- 2FA etkinleştirildikten hemen sonra 30–60 saniye giriş yapılamıyordu (tekrar oynatma sayacı yanlış adımla kaydediliyordu) (`Admin`).
- `<bz:degilse/>` veya `<bz:yoksa-eger>` `<bz:eger>` dışında kullanılınca derlenen PHP bozuluyor ve sayfa 500 veriyordu; derleyici artık güvenli, denetleyici bu durumu bildiriyor (`Template`).
- Kurulumda kaydedilen site adresi, Host başlığında portu iletmeyen sunucularda (ör. Debian'ın güncel nginx paketi) standart dışı portu kaybediyordu; port artık `SERVER_PORT`'tan tamamlanıyor, ters vekil arkasında iç port eklenmiyor (`App::originFrom`).
- Alt klasörlerdeki aynı adlı şablonların derlenmiş önbellek dosyaları çakışıyordu (`Template`).
- IIS (`web.config`) ve Nginx örneğinde yüklenen betik/HTML/SVG dosyaları, veritabanı/günlük dosyaları ve kök geliştirme dosyaları için engeller eklendi.

**Testler**
- Yeni `testler/birim.php`: veritabanı ve sunucu gerektirmeyen birim testleri (temizleyici, derleyici/denetleyici, süzgeçler, doğrulayıcılar, TOTP RFC 6238 vektörleri, README şablon örneği).
- `testler/kapsamli.php`: JSON-LD kaçışı, kontrol karakterli bağlantı ve KVKK bağlantısı için regresyon testleri.
- CI ve yayın iş akışı birim testlerini de çalıştırıyor.

**Belgeler**
- İngilizce README ve `docs/en/` (başlangıç, yapılandırma, mimari, şablon dili, API/MCP/web kancaları, dağıtım, güvenlik, testler, SSS); Türkçe README `README.tr.md`'ye taşındı; `docs/MIMARI.md`, `docs/YAPILANDIRMA.md` ve belge dizini eklendi.
- İki dilli katkı rehberi, davranış kuralları, güvenlik politikası, issue/PR şablonları; `.editorconfig`.

**English summary:** fixes broken KVKK consent markup, a JSON-LD `</script>` break-out (stored XSS), a
sanitizer bypass via control-character `javascript:` URLs, DOM XSS in the panel's paste/SEO preview, possible
plain-text SMTP credentials after a failed STARTTLS, a webhook DNS-rebinding window, authors bypassing
publish review via inline editing, a 2FA enrolment lock-out, the install-time site URL losing a non-standard port, invalid compiled PHP for misplaced
`<bz:degilse/>`, compiled-template cache collisions, and missing IIS/Nginx upload protections. Adds a unit test
suite and English documentation.
