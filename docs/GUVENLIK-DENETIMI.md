# Agresif Güvenlik Denetimi (1.0) — 3 Bakış Açısı

Kapsam: tüm PHP çekirdeği, panel, ön yüz, API, MCP, ödeme, içe/dışa aktarma ve istemci tarafı betikler. Her bulgu kod incelemesi + `testler/kapsamli.php` içindeki otomatik saldırı senaryolarıyla doğrulandı. **Bulunan açıkların tamamı kapatıldı**; kalan riskler en altta açıkça yazılıdır.

Önem: 🔴 kritik · 🟠 yüksek · 🟡 orta · 🔵 düşük

---

## 1. Güvenlik uzmanı (savunma derinliği, yapılandırma, en az yetki)

| # | Bulgu | Önem | Düzeltme |
|---|---|---|---|
| G1 | Yedekten geri yüklemede JSON anahtarları doğrudan sütun adı olarak SQL'e giriyordu (kötü niyetli yedek dosyasıyla SQL enjeksiyonu) | 🔴 | Sütunlar `Db::columns()` izin listesinden; skaler olmayan değerler JSON'a çevrilir; boyut sınırı |
| G2 | Host başlığı zehirlenmesi: şifre sıfırlama e-postası, canonical ve sitemap istek başlığındaki alan adını kullanabilirdi | 🟠 | Kanonik site adresi kurulumda kaydedilir; e-posta/canonical/sitemap her zaman bunu kullanır; önbellek yalnızca kanonik alan adı için yazılır |
| G3 | Panelde CSP, HSTS yoktu | 🟡 | Panel için sıkı CSP (`object-src 'none'`, `frame-ancestors 'self'`, `form-action 'self'`), HTTPS'te HSTS |
| G4 | Oturum: hareketsizlik/mutlak süre sınırı yoktu, şifre değişince diğer oturumlar açık kalıyordu | 🟠 | 2 saat hareketsizlik, 12 saat mutlak süre, `oturum_surumu` ile şifre değişince tüm diğer oturumlar düşer, `use_strict_mode` |
| G5 | Güncelleme deposu panelden değiştirilebiliyordu → ele geçirilmiş yönetici hesabı = uzaktan kod çalıştırma | 🟠 | Depo yalnızca `veri/yapilandirma.php`'den değiştirilebilir; paketler SHA-256 doğrulanır; zip-slip koruması; yalnızca GitHub alan adları |
| G6 | Yazar rolü başkasının içeriğinin sürümünü geri yükleyebiliyor, medya silebiliyordu | 🟡 | Sürüm geri yükleme, kopyalama, satır içi kayıt ve silmede sahiplik kontrolü; medya silme editör+ |
| G7 | Kurulum dosyası silinirse yabancı yeniden kurulum yapabilirdi | 🟠 | `veri/kurulum.kilit` + mevcut veritabanı algılanınca kurulum kilitlenir |
| G8 | `.well-known` dışındaki tüm nokta dosyaları, `.md/.json/.lock` gibi gerçek dosyalar servis edilebiliyordu | 🔵 | `.htaccess` ve geliştirme yönlendiricisi kuralları |
| G9 | API anahtarları | — | Yalnızca SHA-256 özeti saklanır, bir kez gösterilir, kapsamlı (oku/yaz), son kullanım izlenir, iptal edilebilir |

## 2. Etik hacker (sızma testi: OWASP Top 10 + iş mantığı)

| # | Senaryo | Önem | Sonuç / düzeltme |
|---|---|---|---|
| H1 | **Prompt enjeksiyonu → XSS**: zararlı içerik YZ'ye verilir, YZ çıktısı `<img onerror>` döndürür ve panelde çalışır | 🟠 | İstemci tarafı `bzTemizle()` (DOMParser, olay öznitelikleri, `javascript:`/`data:` adresleri, betik/SVG kaldırılır) — YZ çıktısı, yapıştırma, HTML kaynak modu ve otomatik taslak geri yükleme yollarının hepsinde; sunucu kayıtta ayrıca izin listesiyle temizler |
| H2 | **CSV/Excel formül enjeksiyonu**: form alanına `=HYPERLINK(...)` | 🟡 | Tüm CSV dışa aktarımlarında `bz_csv_cell()` (form, sipariş) — test edildi |
| H3 | **Kullanıcı adı tespiti** (zamanlama farkı, şifre sıfırlama yanıtı) | 🟡 | Kullanıcı yoksa da hash doğrulaması; sıfırlama her durumda aynı yanıt — test edildi |
| H4 | **TOTP tekrar oynatma** (aynı kodun 30 sn içinde ikinci kullanımı) | 🟡 | Kullanılan zaman adımı saklanır, eski/aynı adım reddedilir |
| H5 | **Yönlendirme yöneticisiyle `javascript:` hedefi** | 🔵 | Yalnızca `/` veya `http(s)://` hedefleri — test edildi |
| H6 | **Yol geçişi**: `..%2f`, `?yol=../../`, `/sablonlar/../veri`, `/_bakim`, `/parcalar/ust`, `/blog/..%2f.md` | — | Hepsi 403/404 — 9 senaryo otomatik test |
| H7 | **Yetki yükseltme**: yazar → ayarlar, ana sayfayı değiştirme, doğrudan yayınlama | — | Hepsi engelli — test edildi |
| H8 | **XXE** (WordPress içe aktarma) | — | `LIBXML_NONET`, PHP 8 dış varlık yüklemez — `file:///etc/passwd` denemesi test edildi |
| H9 | **Sahte resim içinde PHP** (`kabuk.php.jpg`) | — | İçerik taraması + `finfo` + `yuklemeler/` altında yürütme kapalı — test edildi |
| H10 | **API/MCP yetkisiz erişim**, okuma anahtarıyla yazma, sahte anahtar | — | 401/403 — test edildi; MCP yazma araçları yalnızca taslak oluşturur |
| H11 | **Ödeme tutarı manipülasyonu** | — | Tutar istemciden alınmaz, içerikten okunur; PayTR bildirimi HMAC ile doğrulanır, tutar karşılaştırılır; iyzico sonucu sunucudan sorgulanır; durum geçişi atomik (`WHERE durum='bekliyor'`) |
| H12 | **Yansıyan/depolanan XSS** (arama, form, içerik, API) | — | Varsayılan kaçış + izin listesi temizleyici — test edildi |

## 3. Kötü niyetli saldırgan (otomasyon, kaynak tüketme, zincirleme)

| # | Saldırı | Önem | Düzeltme |
|---|---|---|---|
| K1 | **Vekil başlığı sahteciliği**: `X-Forwarded-For` / `CF-Connecting-IP` ile her denemede farklı IP → kaba kuvvet kilidi atlatma | 🔴 | Vekil başlıklarına yalnızca Ayarlar › Gelişmiş'te açıkça güvenildiğinde bakılır — test edildi |
| K2 | **Dağıtık kaba kuvvet** (botnet, her IP'den birkaç deneme) | 🟠 | IP kilidine ek olarak hesap bazlı kilit (15 dakikada 15 hatalı deneme) |
| K3 | **Önbellek ile disk doldurma**: `?x=rastgele` veya `?sayfa=99999` ile milyonlarca önbellek dosyası | 🟠 | Bilinmeyen sorgu parametreli istekler önbelleğe yazılmaz; uzun adres ve aşırı sayfa numarası yazılmaz; 5.000 dosya üst sınırı — test edildi |
| K4 | **Alan bazlı süzgeçle bellek tüketme** (çok büyük sitede tüm kayıtları belleğe çekme) | 🟡 | PHP tarafı süzme en fazla 5.000 kayıtla sınırlı |
| K5 | **API sele boğma** | 🟡 | Anahtar/IP başına dakikada 120 istek, `429 Retry-After` |
| K6 | **Web kancası / YZ adresiyle SSRF** (iç ağ, bulut metadata servisi), **DNS yeniden bağlama** | 🟠 | Özel/ayrılmış IP aralıkları reddedilir; web kancalarında doğrulanan IP'ye `CURLOPT_RESOLVE` ile sabitleme; yönlendirme izlenmez |
| K7 | **Ödeme başlatma spam'i** (sahte sipariş sel'i) | 🟡 | İmzalı zaman belirteci + IP başına 10 dakikada 10 sipariş |
| K8 | **Form spam'i** | — | Bal küpü, imzalı belirteç (≥3 sn), köken denetimi, IP başına 10 dakikada 5 gönderim — 300 alanlı sahte gönderim testi |
| K9 | **Şifre sıfırlama e-posta bombası** | 🟡 | Sıfırlama istekleri giriş denemesi sayılır; IP başına kilit |
| K10 | **Zincir: yazar hesabı + depolanan XSS + yönetici oturumu** | 🟠 | Sunucu tarafı temizleyici + panel CSP + HttpOnly/SameSite çerez + oturum parmak izi; satır içi düzenleme de aynı kayıt yolundan geçer |

---

## Kalan riskler ve öneriler (bilinçli kabul)

- Panel CSP'si satır içi betiklere izin verir (`'unsafe-inline'`); 1.1'de nonce tabanlı CSP'ye geçilecek.
- Ön yüz için CSP varsayılan değildir (tasarımcı şablonları satır içi betik içerebilir); `.htaccess` ile eklenebilir.
- Yedek dosyaları (`bz_ayarlar` dahil) API anahtarları gibi gizli bilgileri içerir; yedekleri güvenli saklayın.
- PayTR/iyzico entegrasyonları resmi belgelere göre yazıldı ancak gerçek ticari hesapla canlı test edilmedi; canlıya almadan önce test modunda doğrulayın.
- `mail()` yedeği SPF/DKIM olmadan spam'e düşebilir; SMTP kullanın.
