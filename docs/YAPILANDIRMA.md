# Yapılandırma

BOZKURT yapılandırmayı iki yerde tutar:

1. **`veri/yapilandirma.php`** — kurulum sihirbazının yazdığı küçük PHP dosyası; veritabanı açılmadan önce bilinmesi gerekenler.
2. **Veritabanındaki ayarlar** (`bz_ayarlar` tablosu) — geri kalan her şey, **Yönetim › Ayarlar**'dan düzenlenir.

English: [en/configuration.md](en/configuration.md)

## `veri/yapilandirma.php`

```php
<?php
return [
    'kuruldu'       => true,
    'anahtar'       => '…64 onaltılık karakter…', // site gizli anahtarı (random_bytes(32))
    'saat_dilimi'   => 'Europe/Istanbul',
    'dil'           => 'tr',                 // sistem dili (bozkurt/lang/<kod>.php) ve varsayılan site dili
    'guzel_url'     => true,                 // false → index.php?yol=...
    'hata_ayiklama' => false,                // PHP hatalarını ve şablon istisnalarını göster
    'db' => [
        'surucu' => 'sqlite',
        'dosya'  => '/yol/veri/bozkurt-1a2b3c4d5e6f.sqlite',
    ],
    // MySQL için:
    // 'db' => ['surucu' => 'mysql', 'sunucu' => 'localhost', 'port' => 3306,
    //          'ad' => 'veritabani', 'kullanici' => 'kullanici', 'sifre' => 'sifre'],

    // İsteğe bağlı: tek tıkla güncellemenin kullanacağı depo (varsayılan: bozkurt/boot.php içindeki BZ_REPO)
    // 'guncelleme_deposu' => 'sahip/depo',
];
```

| Anahtar | Anlamı |
|---|---|
| `anahtar` | Site gizli anahtarı. Form belirteçlerini imzalar, oturum parmak izinin parçasıdır ve web kancası imza anahtarı girilmemişse web kancalarını imzalar. Gizli tutun; değiştirmek herkesin oturumunu kapatır ve açık formları geçersiz kılar. |
| `saat_dilimi` | Herhangi bir PHP saat dilimi. |
| `dil` | İki harfli kod. `bozkurt/lang/<kod>.php` (`tr` ve `en` hazır) dosyasını seçer; *Diller* ayarı boşsa varsayılan dildir. |
| `guzel_url` | Kurulum, `.htaccess`'li Apache/LiteSpeed veya PHP yerleşik sunucusunu algılarsa açar. **Nginx ve IIS'te** yönlendirme kurallarını ekledikten sonra elle `true` yapın. |
| `hata_ayiklama` | Yalnızca sorun giderirken; yayında asla açık bırakmayın. |
| `db` | Veritabanı bağlantısı. SQLite dosya adı tahmin edilemesin diye rastgele son ek içerir. |

Dosya, hosting izin veriyorsa `0600` izniyle oluşturulur; `veri/` klasörünün tamamı `.htaccess`, `web.config` ve Nginx örneğiyle web erişimine kapalıdır.

## Ayar sekmeleri (Yönetim › Ayarlar)

| Sekme | Başlıca ayarlar |
|---|---|
| **Genel** | Site adı, slogan, açıklama, kanonik site adresi, logo, favicon, bakım modu |
| **Diller** | Etkin diller, ör. `tr,en` (ilki varsayılan; diğerleri `/en/` gibi öneklerle yayınlanır) |
| **SEO ve Pazarlama** | Varsayılan paylaşım görseli (1200×630), Google Search Console ve Yandex doğrulama kodları, GA4, Yandex Metrica, Meta Pixel, robots.txt ek kuralları, arama motorlarını engelle |
| **Yapay Zekâ** | OpenAI uyumlu API adresi, anahtar, model, marka üslubu; robots.txt YZ bot politikası; llms.txt / llms-full.txt; `.md` sürümler; MCP sunucusu |
| **KVKK** | Çerez bandı ve metni, aydınlatma metni sayfası, IP anonimleştirme, form verisi saklama süresi (gün) |
| **E-posta** | Bildirim adresi, SMTP sunucu/port/güvenlik/kullanıcı/şifre, gönderen adresi (SMTP yoksa PHP `mail()`) |
| **Türkiye** | İşletme adı, türü, telefon, adres, çalışma saatleri (LocalBusiness JSON-LD); Netgsm SMS; ek resmî tatiller |
| **Ödeme** | Sağlayıcı (PayTR veya iyzico), test modu, sağlayıcı bilgileri |
| **Performans** | Tam sayfa önbellek ve süresi, en büyük yükleme (MB), en büyük resim genişliği (px) |
| **Gelişmiş** | JSON API okumayı herkese aç, CORS kökeni, web kancası adresleri ve imza anahtarı, ters vekil / Cloudflare başlıklarına güven, beyaz etiket panel adı ve logosu |

SMTP şifresi, YZ anahtarı ve ödeme bilgileri veritabanında saklanır; yedeklerinizi buna göre koruyun.

## Kullanıcılar ve roller

| Rol | Yetkiler |
|---|---|
| `yonetici` | Her şey |
| `editor` | Tüm içerik, medya, formlar, genel alanlar, yayınlama |
| `yazar` | Kendi içeriği ve medya; taslak kaydeder, yayınlayamaz |

Her kullanıcı **Profil**'den TOTP iki adımlı doğrulamayı açabilir ve panel dilini (Türkçe/İngilizce) seçebilir.

## Çeviriler

- Panel arayüzü: `bozkurt/lang/panel-en.php` (sunucu) ve `bozkurt/lang/panel-en-js.php` (JavaScript)
- Şablon metinleri: `sablonlar/diller/<dil>.json` + `cevir` süzgeci
