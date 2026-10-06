# Yol Haritası

## 1.0 — İlk kararlı sürüm ✅
- **Temel:** şablon dili ve derleyici, 26 alan türü, tekil/çoklu içerik, taslak, zamanlama, sürümler, medya (WebP), formlar + KVKK, SEO/JSON-LD/sitemap/RSS, roller + 2FA, yedekleme, tam sayfa önbellek
- **Çok dilli içerik:** `/en/` önekleri, çeviri iş akışı, hreflang, dil seçici, dil başına genel alanlar, şablon metin sözlükleri, RTL desteği
- **Yapay zekâ:** editör yardımcısı, SEO üretimi, toplu çeviri, görselden alt metin, llms.txt / llms-full.txt, `.md` sürümler, YZ bot politikası, **MCP sunucusu**
- **Yazma API'si** (token, kapsam, hız sınırı) ve **web kancaları**
- **Blok düzenleyici**, Markdown alanı, ön yüzde **satır içi düzenleme**
- E-posta ile **şifre sıfırlama**, panelin **İngilizce** dili, beyaz etiket
- Kategori ve **arşiv** etiketleri, **srcset** (`<bz:resim>`, `resimler`)
- **Tek tıkla güncelleme** (SHA-256), **WordPress içe aktarıcı**, **statik site dışa aktarma**
- **301 yönlendirme yöneticisi**, 404 günlüğü, kanonik adres düzeltme, SEO denetimi, canlı SEO analizi
- **Türkiye paketi:** PayTR ve iyzico ödeme, siparişler + e-Fatura CSV, Netgsm SMS, il / TCKN / VKN / IBAN alanları, resmî tatil takvimi, LocalBusiness şeması, İYS onayı
- Pazarlama: UTM yakalama, dönüşüm olayları (GA4, Meta Pixel, dataLayer)
- İçerik takvimi, yerel otomatik taslak, içerik kopyalama, yayına hazırlık listesi

## 1.1 — Kalite
- `Admin.php`'nin bölüm sınıflarına ayrılması
- Nonce tabanlı panel CSP'si
- Sürüm farkı (diff) görünümü
- LCP görseli için otomatik `preload`
- Görsel odak noktası (kırpmada)

## 1.2 — Genişleme
- Çoklu site (tek kurulum, çok alan adı)
- İmzalı eklenti sistemi ve kancalar
- Ödeme: sepet ve stok, kargo entegrasyonları
- e-Fatura entegratörleriyle (ör. Paraşüt, Logo) doğrudan bağlantı
