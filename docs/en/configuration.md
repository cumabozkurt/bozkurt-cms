# Configuration

BOZKURT keeps configuration in two places:

1. **`veri/yapilandirma.php`** — a small PHP file written by the installer. It holds what must be known
   before the database is opened.
2. **Settings in the database** (`bz_ayarlar` table) — everything else, edited in
   **Yönetim › Ayarlar** (Admin › Settings).

## `veri/yapilandirma.php`

```php
<?php
return [
    'kuruldu'       => true,                 // "installed"
    'anahtar'       => '…64 hex characters…', // secret key (random_bytes(32))
    'saat_dilimi'   => 'Europe/Istanbul',    // timezone
    'dil'           => 'tr',                 // system language (bozkurt/lang/<code>.php) and default site language
    'guzel_url'     => true,                 // pretty URLs; false → index.php?yol=...
    'hata_ayiklama' => false,                // debug mode: show PHP errors and template exceptions
    'db' => [
        'surucu' => 'sqlite',                // driver: sqlite | mysql
        'dosya'  => '/path/to/veri/bozkurt-1a2b3c4d5e6f.sqlite',
    ],
    // MySQL instead:
    // 'db' => ['surucu' => 'mysql', 'sunucu' => 'localhost', 'port' => 3306,
    //          'ad' => 'database', 'kullanici' => 'user', 'sifre' => 'password'],

    // Optional: repository used by the one-click updater (default: BZ_REPO in bozkurt/boot.php)
    // 'guncelleme_deposu' => 'owner/repo',
];
```

| Key | Meaning |
|---|---|
| `anahtar` | Site secret. It signs the anti-spam form tokens, is part of the session fingerprint and is the webhook signing key when no webhook secret is set. Keep it private; changing it logs everyone out and invalidates open forms. |
| `saat_dilimi` | Any PHP timezone identifier. |
| `dil` | Two-letter code. Selects `bozkurt/lang/<code>.php` (`tr` and `en` ship) and is the fallback when the *Diller* setting is empty. |
| `guzel_url` | The installer enables it when it detects Apache/LiteSpeed with `.htaccess`, or PHP's built-in server. On **Nginx and IIS set it to `true` manually** after configuring rewrites (see [Deployment](deployment.md)). |
| `hata_ayiklama` | Only for troubleshooting; never leave it on in production. |
| `db` | Database connection. The SQLite file name contains a random suffix so it cannot be guessed. |

The file is created with mode `0600` where the host allows it. The whole `veri/` directory is blocked from
web access by `.htaccess`, `web.config` and the Nginx example.

## Settings tabs (Yönetim › Ayarlar)

| Tab | Main settings |
|---|---|
| **Genel** (General) | Site name, slogan, description, canonical site URL, logo, favicon, maintenance mode. |
| **Diller** (Languages) | Enabled languages, e.g. `tr,en` (first one is the default; others get `/en/` style prefixes). |
| **SEO ve Pazarlama** | Default share image (1200×630), Google Search Console and Yandex verification codes, GA4 ID, Yandex Metrica ID, Meta Pixel ID, extra `robots.txt` rules, "discourage search engines". |
| **Yapay Zekâ** (AI) | OpenAI-compatible API URL, key and model, brand tone; AI crawler policy for `robots.txt` (allow search bots/block training bots, allow all, block all); publish `llms.txt`/`llms-full.txt`; `.md` page versions; enable the MCP server. |
| **KVKK** | Cookie consent banner and text, privacy-notice page, IP anonymisation, form data retention (days). |
| **E-posta** | Notification address, SMTP host/port/encryption/user/password, sender address. Without SMTP, PHP `mail()` is used. |
| **Türkiye** | Business name, type (LocalBusiness, Restaurant, Store, Dentist …), phone, address, opening hours (for LocalBusiness JSON-LD); extra holidays for the calendar; Netgsm SMS. |
| **Ödeme** (Payments) | Provider (PayTR or iyzico), test mode and provider credentials. |
| **Performans** | Full-page cache and its lifetime, maximum upload size (MB), maximum image width (px). |
| **Gelişmiş** (Advanced) | Public read access to the JSON API, CORS origin, webhook URLs and signing secret, trust reverse-proxy / Cloudflare headers, white-label panel name and logo. |

Secrets such as the SMTP password, AI key and payment credentials are stored in the database, so protect
backups accordingly.

## Users and roles

| Role | Permissions |
|---|---|
| `yonetici` (administrator) | Everything. |
| `editor` | All content, media, forms, global fields, publishing. |
| `yazar` (author) | Own content and media; can save drafts but not publish. |

Each user can enable TOTP two-factor authentication and choose the panel language (Turkish/English) under **Profil**.

## Template-level configuration

Content types, fields, lists and forms are configured in the templates themselves with `<bz:*>` tags —
see [template-language.md](template-language.md).

## Translating strings

- Panel UI: `bozkurt/lang/panel-en.php` (server-rendered) and `bozkurt/lang/panel-en-js.php` (JavaScript).
- Front-end template strings: `sablonlar/diller/<lang>.json` used with the `cevir` filter.
