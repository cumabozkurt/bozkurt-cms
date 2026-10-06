# 25 Popüler CMS Reposundan Çıkarılan Dersler

Yıldız sayıları GitHub'dan Ekim 2026'da alındı (yuvarlanmış). Amaç, “bugün sıfırdan bir CMS yazılsaydı neler olmalı?” sorusuna kanıta dayalı yanıt vermek ve BOZKURT'a ne aldığımızı/neyi bilerek almadığımızı göstermek.

## Tablo

| # | Proje | ⭐ | Dil / tür | En iyi yaptığı | BOZKURT'a aldığımız |
|---|---|---|---|---|---|
| 1 | strapi/strapi | 73k | Node, headless | İçerik türü oluşturucu, rol/izin, REST+GraphQL | Şablondan otomatik içerik türü, roller, JSON API |
| 2 | TryGhost/Ghost | 55k | Node, yayıncılık | Sade yazma deneyimi, SEO varsayılanları, bülten | Odaklı editör, otomatik OG/JSON-LD, okuma süresi |
| 3 | payloadcms/payload | 45k | Node/TS, kod-önce | Alanları kodda tanımlama, sürümler, taslak | Alanları şablonda tanımlama, 25 sürüm geçmişi, taslak/zamanlama |
| 4 | directus/directus | 38k | Node, veri platformu | Her veritabanına anında API, dosya kütüphanesi | DB'den bağımsız katman, medya kütüphanesi, API |
| 5 | WordPress/WordPress | 21k | PHP | Ekosistem, tek tıkla kurulum, medya | 5 dakikalık kurulum, sürükle-bırak medya, ön yüz düzenleme çubuğu |
| 6 | getgrav/grav | 16k | PHP, düz dosya | Veritabanısız, Twig, hızlı önbellek | Derlenmiş şablon + tam sayfa önbellek, SQLite ile “neredeyse dosya” sadeliği |
| 7 | octobercms/october | 11k | PHP/Laravel | Geliştirici dostu, bileşenler | Temiz klasör yapısı, parça (`dahil`) sistemi |
| 8 | pagekit/pagekit | 5.5k | PHP/Vue | Modern panel (2016'da), modülerlik | Modern panel tasarım dili (artık bakımı yok — ders: bağımlılığı az tut) |
| 9 | joomla/joomla-cms | 5.1k | PHP | Çok dilli çekirdek, ACL, 2FA | 2FA, rol tabanlı yetki |
| 10 | statamic/cms | 4.9k | PHP/Laravel | Düz dosya + panel, “blueprint” alan tanımı, Antlers şablon dili | Alan grupları (`grup`), şablon dilinde süzgeç zinciri |
| 11 | drupal/drupal | 4.3k | PHP | İçerik modelleme, görünümler, güvenlik ekibi | İlişki alanı, liste süzme/sıralama, güvenlik politikası |
| 12 | picocms/Pico | 3.9k | PHP, düz dosya | Aşırı sadelik | Sıfır ayarla çalışma |
| 13 | craftcms/cms | 3.6k | PHP/Yii | Matrix (tekrarlanan bloklar), canlı önizleme | `tekrar` alanı, önizleme |
| 14 | microweber/microweber | 3.4k | PHP/Laravel | Sürükle-bırak sayfa oluşturucu, e-ticaret | (Yol haritası: blok tabanlı sayfa oluşturucu) |
| 15 | wintercms/winter | 1.5k | PHP/Laravel | October'ın topluluk çatalı, açık yönetim | Topluluk yönetişimi, MIT lisans |
| 16 | bludit/bludit | 1.5k | PHP, JSON dosya | Basit kurulum, çok dilli panel (Türkçe dahil) | Türkçe panel, tek ekran kurulum |
| 17 | getkirby/kirby | 1.5k | PHP, düz dosya | “Panel”in alan tanımları, mükemmel belge | Alan türü zenginliği, kapsamlı Türkçe belge |
| 18 | danpros/htmly | 1.4k | PHP, düz dosya | Veritabanısız blog, hız | Blog odaklı varsayılan şablon, RSS |
| 19 | processwire/processwire | 1.2k | PHP | Esnek alan/şablon modeli, API | Şablon = içerik türü fikri |
| 20 | backdrop/backdrop | 1k | PHP | Drupal 7 basitleştirilmesi, geriye uyum | Kararlı, sade API sözü |
| 21 | marcantondahmen/automad | 940 | PHP, düz dosya | Blok editörü, tasarım dostu şablon dili | Tasarımcı dostu etiket sözdizimi |
| 22 | textpattern/textpattern | 880 | PHP | Etiket tabanlı şablon (Couch'a en yakın) | Etiket yaklaşımının modernleştirilmesi |
| 23 | concretecms/concretecms | 851 | PHP | Sayfa üzerinde düzenleme | “Bu sayfayı düzenle” çubuğu |
| 24 | WonderCMS/wondercms | 740 | PHP, tek dosya | Tek dosya, sayfa içi düzenleme | Küçük çekirdek, FTP ile yükle-çalıştır |
| 25 | typemill/typemill | 620 | PHP, düz dosya | Markdown, dokümantasyon siteleri | (Yol haritası: Markdown alanı) |

Referans: CouchCMS/CouchCMS ≈ 380 ⭐.

## Ortak başarı örüntüleri

1. **İlk 5 dakika her şeydir.** En çok yıldız alan PHP projeleri (WordPress, Grav, October, Pico) kurulumu tek adıma indirir. → BOZKURT: FTP + tek form, SQLite ile veritabanı ayarı bile yok.
2. **İçerik modeli koddan/şablondan gelir.** Payload, Statamic, Kirby, Craft alanları dosyada tanımlar, panel bunlardan üretilir. → BOZKURT: alanlar HTML içinde tanımlanır, panel otomatik.
3. **Headless seçeneği artık standart.** İlk 4 projenin tamamı API-öncelikli. → BOZKURT: JSON API (salt okunur, anahtar korumalı).
4. **Yazma deneyimi ayrıştırıcıdır.** Ghost ve Craft, editör kalitesiyle öne çıkar. → Temiz editör, Word'den yapıştırma temizliği, Ctrl+S, kaydedilmemiş değişiklik uyarısı, SEO önizleme.
5. **Sürüm ve taslak** (Payload, Craft, Strapi). → Taslak, zamanlanmış yayın, önizleme, sürüm geri yükleme.
6. **Performans varsayılan olmalı** (Grav, HTMLy). → Derleme + tam sayfa önbellek + WebP.
7. **Güvenlik varsayılan olmalı** (Joomla 2FA, Drupal güvenlik ekibi). → 2FA, kilit, CSRF, izin listesi temizleyici, kilitli klasörler.
8. **Bağımlılık yükü projeyi öldürebilir.** Pagekit (5.5k ⭐) Vue/Composer yığınının bakım yüküyle durdu. → BOZKURT'ta Composer, npm, derleme adımı yok.
9. **Belgelendirme yıldız getirir** (Kirby, Grav). → Türkçe, örnekli şablon rehberi.
10. **Lisans özgürlüğü topluluk getirir** (Winter vs. October lisans tartışması). → MIT.

## Bilerek almadıklarımız

- **Eklenti pazarı ve kanca (hook) sistemi** — WordPress'in en büyük güvenlik yüzeyi. v1'de yok; v2'de imzalı eklentilerle planlandı.
- **Sayfa oluşturucu (Microweber, Elementor tarzı)** — tasarımcının HTML'ine saygı ilkemizle çelişir; blok alanı olarak yol haritasında.
- **GraphQL** — paylaşımlı hostingde gereksiz ağırlık; REST JSON yeterli.
- **Node/Laravel bağımlılığı** — Hostinger paylaşımlı planlarında Node yok, Composer erişimi sınırlı.
