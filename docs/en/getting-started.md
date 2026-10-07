# Getting started

This guide takes you from an empty hosting account (or your laptop) to a running BOZKURT CMS site.

> BOZKURT is Turkish-first: the admin panel defaults to Turkish and template tags use Turkish words.
> Each admin user can switch the panel to English under **Profil** (Profile). Translations of the
> Turkish terms you will meet are given in parentheses throughout these docs.

## 1. Requirements

| Requirement | Notes |
|---|---|
| PHP 8.1 or newer | 8.3 recommended. The installer blocks older versions. |
| `pdo_sqlite` **or** `pdo_mysql` | At least one is required. SQLite needs no setup. |
| `mbstring`, `fileinfo`, `dom` | Required (the installer checks them). `dom` powers the HTML sanitizer. |
| `gd` | Recommended: image thumbnails and WebP conversion. |
| `zip` (ZipArchive) | Recommended: backups, static export, one-click updater. |
| `curl` | Recommended: AI assistant, webhooks, payment and SMS providers, updater. |
| Writable `veri/` and `yuklemeler/` | Data directory and media uploads. |
| Web server | Apache/LiteSpeed (`.htaccess` included), Nginx (`nginx.conf.ornek`) or IIS (`web.config`). |

No Composer, npm or build step is needed.

## 2. Get the code

No GitHub release has been published yet. Until one exists, download the current `main` branch with
**Code › Download ZIP** on GitHub, or clone it:

```bash
git clone https://github.com/cumabozkurt/bozkurt-cms.git
```

When releases are published, each one ships a `bozkurt-cms-x.y.z.zip` plus a `.sha256` checksum (built by
`.github/workflows/yayin.yml`). The release ZIP leaves out tests, CI files and screenshots (see `.gitattributes`).

## 3. Install on shared hosting

1. Upload every file to your web root (for example `public_html/`) with the hosting file manager or FTP.
   Make sure the hidden `.htaccess` file is uploaded too.
2. Select PHP 8.1+ in your hosting control panel.
3. If you want MySQL, create a database and a user in the control panel first. Otherwise choose SQLite.
4. Visit `https://your-domain.com/yonetim/` (`yonetim` = administration). The installer:
   - checks the requirements listed above;
   - asks for the site name, your name, e-mail and a password (at least 10 characters);
   - asks for the database driver (SQLite or MySQL/MariaDB with host, port, database, user, password);
   - optionally loads **sample content** (demo pages, blog posts, FAQ, contact form).
5. After you submit, BOZKURT creates the database tables, the first administrator account and
   `veri/yapilandirma.php`, writes a `veri/kurulum.kilit` lock file and logs you in.

A step-by-step guide with Hostinger screenshots-in-words and an FAQ is available in Turkish:
[HOSTINGER-KURULUM.md](../HOSTINGER-KURULUM.md).

### Installing in a sub-folder

The base path is detected automatically. On Apache/LiteSpeed, edit `.htaccess` and change
`RewriteBase /` to `RewriteBase /your-folder/`.

### Re-installing

The installer refuses to run while a SQLite database or `veri/kurulum.kilit` exists, so nobody can
re-install over a live site after a configuration file is lost. To start over, remove
`veri/yapilandirma.php`, `veri/kurulum.kilit` and the `veri/*.sqlite` file over FTP.

## 4. Local development

```bash
cd bozkurt-cms
php -S localhost:8000 yonlendirici.php
```

Open <http://localhost:8000/yonetim/> and run the installer. `yonlendirici.php` ("router") emulates the
`.htaccess` rules for PHP's built-in server: it blocks protected directories and routes everything else to
`index.php`, so pretty URLs work locally.

## 5. First steps in the panel

| Panel item (Turkish) | What it is |
|---|---|
| **Panel** | Dashboard: recent content, form messages, go-live checklist. |
| Content types (e.g. *Ana Sayfa*, *Blog*) | One entry per template in `sablonlar/`. Single pages open the editor directly; multi-entry templates show a list. |
| **Genel Alanlar** | Global fields (phone, address, social links …) defined with `<bz:genel>`. |
| **İçerik Takvimi** | Content calendar with Turkish public holidays. |
| **Medya** | Media library (uploads are resized, converted to WebP thumbnails, EXIF/GPS stripped). |
| **Form Mesajları** | Form submissions, CSV export. |
| **SEO Denetimi**, **Yönlendirmeler** | Site-wide SEO audit; 301 redirects and the 404 log. |
| **Kullanıcılar**, **Ayarlar**, **API ve MCP**, **Araçlar** | Users and roles, settings, API keys, tools (updater, WordPress import, static export). |
| **Yedekleme**, **Etkinlik Günlüğü**, **Sistem Durumu** | Backups, activity log, system information. |

## 6. Create your first template

Templates are plain HTML files in `sablonlar/`. Every `.html` file (except `404.html` and names starting
with `_`) becomes a content type and a URL. See the 60-second example in the [README](../../README.md#a-template-in-60-seconds)
and the full [template language reference](template-language.md).

## Next steps

- [Configuration](configuration.md) — the config file and every settings tab
- [Deployment](deployment.md) — production checklist for Apache, Nginx and IIS
- [REST API, MCP and webhooks](api.md)
- [FAQ](faq.md)
