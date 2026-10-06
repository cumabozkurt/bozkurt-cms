# Değişiklik Günlüğü

## 1.0.0 — 2026-10-07
İlk kararlı sürüm.

**Çekirdek**
- Derlenen şablon dili (`<bz:*>` etiketleri, `{{ }}` değişkenler, 30+ süzgeç), şablon denetleyici
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
