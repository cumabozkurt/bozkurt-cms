# 5 Uzman Gözüyle Uçtan Uca İnceleme (1.0)

Bu inceleme, ilk taslak ve yol haritası tamamlandıktan sonra, 1.0.0 yayınlanmadan önce, beş farklı rolün önceliklerine göre yapıldı. Her bulgunun durumu belirtilmiştir: **✅ uygulandı**, **🔜 yol haritasında** (bilerek ertelendi, gerekçesiyle).

---

## 1. Yazılım uzmanı (mimari, bakım, kalite)

| # | Bulgu | Etki | Durum |
|---|---|---|---|
| Y1 | Panel, API, MCP ve içe aktarıcı içerik kaydını ayrı ayrı yapıyordu → tutarsızlık riski | Yüksek | ✅ Tek kayıt noktası `Content::saveEntry()` (doğrulama, sürüm, 301, önbellek, web kancası hepsi tek yerde) |
| Y2 | Veritabanı şeması sürümlenmiyordu; yükseltmede tablo/sütun eksik kalabilirdi | Yüksek | ✅ `App::SCHEMA` + `ensureSchema()`: eski kurulum ilk istekte sessizce yükseltilir; `addColumn()` güvenli. ilk taslaktan yükseltme test edildi |
| Y3 | Şablon önbelleği yalnızca dosya tarihine bakıyordu; FTP ile eski tarihli dosya yüklenince yeni şablon görünmüyordu (yükseltme testinde bulundu) | Yüksek | ✅ İçerik imzası (yol + boyut + tarih) ile önbellek anahtarı |
| Y4 | Tasarımcı şablon hatasını ancak sayfa bozulunca fark ediyordu | Orta | ✅ `Template::lint()` — panelde bilinmeyen/kapatılmamış etiket, geçersiz alan adı, bilinmeyen tür uyarısı |
| Y5 | Yalnızca duman testi vardı | Yüksek | ✅ 105 kontrollü `testler/kapsamli.php` (çok dil, API, MCP, güvenlik regresyonları) + PHPStan seviye 5 + CI matrisi (PHP 8.1–8.4) |
| Y6 | Kurulum öncesi herhangi bir ayar okuması boş SQLite dosyası oluşturuyordu → kurulum kilidi yanlışlıkla devreye giriyordu | Yüksek | ✅ Kurulum öncesi ayar okumaları veritabanına dokunmaz |
| Y7 | `Admin.php` büyük bir denetleyici | Düşük | 🔜 1.1'de bölüm başına sınıflara ayrılacak (davranış değişmeden) |
| Y8 | Panel metinleri koda gömülü | Orta | ✅ Panel İngilizce sözlüğü (455 metin) + kullanıcı başına panel dili; kullanıcı içeriğine dokunmayan güvenli çeviri katmanı |

## 2. İçerik editörü (günlük kullanım)

| # | Bulgu | Durum |
|---|---|---|
| E1 | Tarayıcı çökerse yazılanlar kayboluyordu | ✅ Yerel otomatik taslak (1,5 sn'de bir), geri yükle / yok say |
| E2 | Yazıyı kopyalayıp benzerini üretmek zahmetliydi | ✅ “Kopyala” → taslak kopya |
| E3 | Uzunluk hissi yoktu | ✅ Editörde canlı kelime sayısı ve okuma süresi |
| E4 | Yazım hataları, özet, başlık bulma | ✅ ✨ YZ yardımcısı: yazım düzeltme, özet, 5 başlık önerisi, serbest komut |
| E5 | Çeviri iş akışı yoktu | ✅ Dil sekmeleri, “+ English” ile kaynaktan çeviri oluşturma, ✨ tüm alanları tek tıkla çevirme, listede çeviri durumu rozetleri |
| E6 | Ne zaman ne yayınlanacağı görünmüyordu | ✅ İçerik takvimi (resmî tatiller işaretli), panelde zamanlanmış yayınlar |
| E7 | Sayfada gördüğünü düzeltmek için panele gitmek gerekiyordu | ✅ Ön yüzde “Sayfada düzenle” (satır içi düzenleme, metin/zengin alanlar) |
| E8 | Esnek sayfa düzeni yoktu | ✅ Blok düzenleyici (`<bz:bloklar>`): metin, alıntı, görsel, eylem çağrısı… tasarımcının tanımladığı bloklar |
| E9 | Markdown yazmayı sevenler | ✅ `tur="markdown"` alanı |
| E10 | Alt metni unutulan görseller | ✅ Medyada “alt metni eksik” süzgeci + ✨ görselden otomatik alt metin |
| E11 | Sürüm karşılaştırma (fark görünümü) | 🔜 1.1 |

## 3. Yazılımı isteyen firma (sahiplik, risk, maliyet)

| # | Bulgu | Durum |
|---|---|---|
| F1 | Ajanslar müşteriye kendi markasıyla teslim etmek ister | ✅ Beyaz etiket: panel adı ve logosu |
| F2 | “Yayına hazır mıyız?” sorusu | ✅ Panelde yayına hazırlık listesi (2FA, SMTP, KVKK metni, doğrulama kodları, ilk yedek) |
| F3 | Güncelleme korkusu | ✅ Tek tıkla güncelleme: SHA-256 doğrulama, öncesinde otomatik yedek, `veri/ sablonlar/ tema/ yuklemeler/` korunur |
| F4 | WordPress'ten geçiş maliyeti | ✅ WXR içe aktarıcı + eski adreslerden otomatik 301 |
| F5 | Satış yapabilmeli | ✅ PayTR ve iyzico ile ödeme düğmesi, siparişler ekranı, e-Fatura için CSV |
| F6 | Telefonunu kaybeden çalışan | ✅ Yönetici başka kullanıcının 2FA'sını sıfırlayabilir; şifre değişince diğer oturumlar kapanır |
| F7 | Şifremi unuttum | ✅ E-posta ile güvenli sıfırlama |
| F8 | Lisans ve bağımlılık riski | ✅ MIT, sıfır Composer/npm bağımlılığı |
| F9 | Çoklu site (tek kurulumdan birden çok alan adı) | 🔜 1.2 — paylaşımlı hostingde her siteye ayrı kurulum hâlâ en güvenli yol |

## 4. Dijital pazarlama uzmanı (ölçüm, dönüşüm)

| # | Bulgu | Durum |
|---|---|---|
| P1 | Form dönüşümleri ölçülmüyordu | ✅ Başarılı gönderimde `dataLayer` (`bz_form_gonderildi`), GA4 `generate_lead`, Meta `Lead` olayı |
| P2 | Hangi kampanyanın lead getirdiği bilinmiyordu | ✅ UTM (`utm_*`, `gclid`, `fbclid`), giriş sayfası ve yönlendiren site her form kaydına eklenir, CSV'ye çıkar |
| P3 | Meta reklamları için Pixel | ✅ Meta Pixel, KVKK onayından sonra yüklenir |
| P4 | Ticari ileti onayı (İYS) | ✅ `<bz:iys-onay>` — onay zamanı ve kanallar kayda yazılır |
| P5 | Anlık lead bildirimi | ✅ Netgsm SMS bildirimi + web kancası (Zapier/Make/n8n/Slack) |
| P6 | Yerel işletme görünürlüğü | ✅ LocalBusiness şeması (Google ve Yandex Haritalar) |
| P7 | Pop-up, A/B testi | 🔜 Bilerek dışarıda: performans ve KVKK yükü; GTM ile yapılabilir |

## 5. SEO uzmanı (teknik SEO, uluslararası, YZ görünürlüğü)

| # | Bulgu | Durum |
|---|---|---|
| S1 | `/Blog/`, `/blog/`, `/blog` aynı içerik (yinelenen içerik) | ✅ Büyük harf, sondaki eğik çizgi ve `/index.php` için 301 kanonik yönlendirme |
| S2 | Adres değişince sıralama kaybı | ✅ Slug değişince otomatik 301; elle/toplu yönlendirme; 410 desteği |
| S3 | 404'ler görünmüyordu | ✅ 404 günlüğü (bot gürültüsü süzülür) + tek tıkla yönlendirme |
| S4 | Çok dilli SEO | ✅ `/en/` önekleri, `hreflang` + `x-default`, sitemap'te `xhtml:link`, `<html lang>` ve `og:locale` otomatik |
| S5 | Görsel SEO | ✅ Sitemap'te `image:image`, `<bz:resim>` ile srcset/sizes, WebP |
| S6 | Arama sonuç sayfaları dizine giriyordu | ✅ Şablon bazlı `noindex` (arama sayfası varsayılan noindex) |
| S7 | İçerik düzeyinde SEO rehberi yoktu | ✅ Canlı Türkçe SEO analizi (odak kelime: başlık/açıklama/ilk paragraf/adres/yoğunluk, H2, alt metin, iç bağlantı, uzunluk) ve puan |
| S8 | Site geneli denetim | ✅ SEO Denetimi ekranı: uzun başlık, eksik açıklama, yinelenen başlık, alt metinsiz görsel, kırık iç bağlantı |
| S9 | FAQ zengin sonuçları | ✅ `schema="FAQPage"` ile otomatik FAQ şeması |
| S10 | YZ yanıt motorlarında görünürlük (GEO) | ✅ `llms.txt`, `llms-full.txt`, her sayfanın `.md` sürümü (`<link rel="alternate" type="text/markdown">`), robots.txt'de YZ bot politikası (eğitim botlarını engelle, arama botlarına izin ver) |
| S11 | Makale tarihleri | ✅ `article:published_time` / `modified_time`, JSON-LD `dateModified` |
| S12 | LCP görseli ön yükleme | 🔜 1.1 (`<bz:resim yukleme="eager">` şimdilik kullanılabilir) |
