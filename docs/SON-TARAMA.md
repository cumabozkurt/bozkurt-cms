# Son Tarama — 5 Açıdan Eksik, Hata ve Yanlış Kontrolü (1.0)

Tüm geliştirme, uzman incelemesi ve güvenlik denetimi bittikten sonra, kod üzerinde beş bağımsız açıdan son bir tarama yapıldı. Bu taramada bulunan sorunlar aynı turda düzeltildi ve testler yeniden koşturuldu.

## 1. Statik doğruluk

- **PHP söz dizimi:** depodaki tüm `.php` dosyaları `php -l` ile denetlendi → hatasız.
- **PHPStan seviye 5** (`phpVersion: 80100`, `phpstan.neon.dist`) → **0 hata**. Bulunup düzeltilenler: IBAN mod-97 hesabında metin/sayı işlem karışıklığı, API yol ayrıştırmada tür uyumsuz `strlen` geri çağırması.
- **JavaScript:** `node --check yonetim/assets/yonetim.js` → hatasız.
- **Bulunan yanlışlar:** Türkçe tırnak işaretinin (“ ”) PHP'de değişken adının parçası sayılması nedeniyle iki hata mesajında boş değişken uyarısı (`"“$k”"`) → `{$k}` ile düzeltildi; depo genelinde aynı kalıp tarandı, başka örnek yok.

## 2. Uyumluluk

- **PHP 8.1 tabanı:** 8.2+ özellikleri için tarama (`readonly class`, `json_validate`, `mb_str_pad`, `array_find`, `Random\Randomizer`, tipli sınıf sabitleri, `#[\Override]`, DNF türleri) → kullanım yok. CI matrisi 8.1, 8.2, 8.3, 8.4.
- **Sürüm yükseltme:** İlk taslak sürümle kurulup içerik eklenmiş site üzerine 1.0.0 kodu kopyalandı → şema otomatik yükseltildi, tüm sayfalar ve panel temiz. **Bu testte gerçek bir hata bulundu:** FTP/arşivden gelen dosyaların eski tarihi nedeniyle yeni şablonlar (Hizmetler, SSS) 404 veriyordu → şablon önbelleği tarih yerine içerik imzasına geçirildi, yeniden test edildi.
- **Sunucular:** Apache/LiteSpeed (`.htaccess`), Nginx (`nginx.conf.ornek`), IIS (`web.config`), PHP yerleşik sunucu (`yonlendirici.php`). `Authorization` başlığı paylaşımlı hostinglerde PHP'ye iletilir; `.well-known` (SSL sertifika doğrulaması) erişime açık bırakıldı.

## 3. İşlevsel bütünlük (uçtan uca)

`testler/kapsamli.php` → **105 kontrol, 0 hata**; `testler/duman.php` → tümü geçti. Kapsam: kurulum ve kilit, 21 panel ekranı, ayar doğrulama, bloklu içerik, 301 otomatiği, çeviri oluşturma, `/en/` yönlendirmesi, hreflang, şablon çevirisi, sitemap alternatifleri, llms.txt/llms-full.txt/.md, robots YZ politikası, LocalBusiness, API okuma/yazma/PATCH/DELETE, MCP initialize/bildirim/araç listesi/arama/taslak, form + dönüşüm olayı, CSV, yönlendirme yöneticisi, 404 günlüğü, satır içi düzenleme, WordPress içe aktarma + XXE, statik dışa aktarma, kanonik adresler, saldırı senaryoları ve yetki sınırları. Sunucu hata günlüğü test sonunda boş.

## 4. Belgeler ↔ kod tutarlılığı

- Derleyicinin tanıdığı her `<bz:…>` etiketi, her alan türü ve her süzgeç `docs/SABLON-REHBERI.md`'de belgelendi (eksik olan `bloklar`, `markdown`, `il`, `tckn`, `vkn`, `iban`, `cevir`, `resimler`, `tatil`, `dil-secici`, `arsiv`, `kategoriler`, `resim`, `odeme`, `iys-onay`, `noindex`, `llms`, `api`, `cevrilebilir` eklendi).
- Kodda okunan her `App::setting()` anahtarının panelden kaydedilebildiği otomatik olarak karşılaştırıldı → eksik yok. Güncelleme deposu ayarı güvenlik gereği panelden kaldırıldı; arayüzdeki ve belgedeki atıflar buna göre düzeltildi.
- Şablon denetleyici (`Template::lint`) örnek şablonların tamamında çalıştırıldı → uyarı yok.

## 5. Kullanıcı deneyimi ve yerelleştirme

- Panel İngilizce ekran görüntüleriyle kontrol edildi; menü, sekmeler, düğmeler, uyarılar çevriliyor. Kullanıcı içeriği (başlıklar, editör, form değerleri) çeviri katmanından korunuyor.
- **Bulunan eksik:** İngilizce sitede menü, ekmek kırıntısı ve liste sayfası başlıkları Türkçe kalıyordu → şablon başlıkları da `cevir` sözlüğünden geçirildi (`sablonlar/diller/en.json`).
- Türkçe yazım/karakter: İ/ı dönüşümü, slug, tarih, ₺, telefon, IBAN biçimleri birim testleriyle doğrulandı (`tckn`, `iban` örnek doğru/yanlış değerlerle).

## Bilinen sınırlar (hata değil, kapsam kararı)

- Panel İngilizce seçildiğinde şablondan gelen alan adları ve yardım metinleri (ör. “Kapak görseli”) tasarımcının yazdığı dilde kalır; bunlar panel değil site içeriğidir.
- PayTR ve iyzico gerçek ticari hesapla canlı test edilmedi (sandbox/test modunda deneyin).
- Çoklu site (tek kurulum, çok alan adı) 1.2 yol haritasındadır; "multi CMS" bu sürümde çok dilli içerik olarak uygulanmıştır.

---

## 1.0.0 yayın öncesi uçtan uca kontrol

| Kontrol | Sonuç |
|---|---|
| `php -l` (tüm PHP dosyaları), `node --check` | Hatasız |
| PHPStan seviye 5 | 0 hata |
| `testler/kapsamli.php` — SQLite | 105 / 105 |
| `testler/kapsamli.php` — MySQL (MariaDB) | 105 / 105 |
| `testler/tarama.php` — tüm site iki dilde gezildi: her iç bağlantı, CSS/JS/görsel/srcset, sitemap'teki her adres, llms.txt'deki her bağlantı; sayfa başına doctype, lang, title, meta açıklama, canonical, tek H1, alt metin, yinelenen id, etiketsiz form alanı, JSON-LD; 33 panel ekranı Türkçe ve İngilizce; gerçek JPEG yükleme ve küçültme; yedek → içerik ekle → geri yükle → sayılar birebir | 39 / 39 |
| `testler/duman.php` | Geçti |
| İlk taslaktan 1.0.0'a yükseltme (veri korunarak) | Tüm sayfalar ve panel temiz, hata günlüğü boş |
| Tarayıcıda (Chromium) panel gezintisi, blok ekleme + kaydetme, 390 px mobil görünüm | JS hatası yok, yatay taşma yok |

**Bu turda bulunup düzeltilen hatalar**
1. İngilizce ana sayfanın Markdown bağlantısı `/en.md` 404 veriyordu ve 404 sayfası kendi `.md` bağlantısını üreterek sonsuz bağlantı zinciri oluşturuyordu → `/en.md` desteklendi, 404 ve noindex sayfalarında `.md` bağlantısı kaldırıldı.
2. Alt bilgideki KVKK bağlantısı İngilizce sitede `/enkvkk` oluyordu → şablonlar için `site.onek` değişkeni eklendi; ana sayfa, 404 ve alt bilgi bağlantıları dile duyarlı.
3. Kısa özetli sayfalarda meta açıklama 20 karakterin altında kalıyordu → kısa özet site açıklamasıyla tamamlanıyor.
4. Kategori/arşiv/sayfa listelerinde başlıklar yineleniyordu → süzgece göre ayrı başlık, kategori sayfalarında kendi canonical'ı, tarih arşivlerinde `noindex, follow`.
5. Henüz çevrilmemiş tekil sayfalar (varsayılan dilden otomatik kopya) İngilizce olarak dizine açılıyor ve hreflang/sitemap'e giriyordu → kaydedilene kadar `noindex`, hreflang ve sitemap dışında.
