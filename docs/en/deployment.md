# Deployment

BOZKURT runs on ordinary shared hosting as well as on a VPS. The repository ships configuration for the
three common web servers. In every case the goals are the same:

- route every request that is not a real file to `index.php` (and `/yonetim/` to `yonetim/index.php`);
- **deny** web access to `veri/`, `bozkurt/`, `sablonlar/`, `testler/`, `bin/`, `docs/`, `.github/` and dotfiles;
- never execute or render scripts/HTML/SVG uploaded to `yuklemeler/`;
- never serve database, log or lock files, nor development files (`composer.json`, `yonlendirici.php` …).

## Apache / LiteSpeed (most shared hosts)

Nothing to do: `.htaccess` (root), `veri/.htaccess` and `yuklemeler/.htaccess` implement the rules above,
forward the `Authorization` header to PHP (needed for API/MCP keys), set long-lived cache headers for static
assets and enable compression when `mod_deflate` is available.

Optional edits in the root `.htaccess`:

- **Force HTTPS** — uncomment the two `RewriteCond %{HTTPS} off` / `RewriteRule` lines once SSL is active.
- **Sub-folder install** — change `RewriteBase /` to `RewriteBase /sub-folder/`.

The installer turns on pretty URLs automatically when it detects Apache/LiteSpeed with `.htaccess`.

## Nginx (VPS)

Copy the rules from [`nginx.conf.ornek`](../../nginx.conf.ornek) into your `server { … }` block and adapt
the PHP-FPM block (socket path). Make sure `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` is
present so API keys reach PHP. Then edit `veri/yapilandirma.php` and set:

```php
'guzel_url' => true,
```

(The installer cannot detect Nginx rewrites, so it falls back to `index.php?yol=…` URLs until you change this.)

Debian's current nginx package passes `HTTP_HOST` without the port (`fastcgi_param HTTP_HOST $host`).
BOZKURT then completes a non-standard port from `SERVER_PORT` when it records the site URL at installation
(unless proxy headers such as `X-Forwarded-For` are present). You can always correct the canonical address
under Settings › Genel › *Sitenin kanonik adresi*.

## IIS (Windows / Plesk)

[`web.config`](../../web.config) requires the IIS URL Rewrite module. It sets `index.php` as the default
document, disables directory browsing, returns 403 for protected paths, uploaded scripts and sensitive file
types, and rewrites everything else to `index.php`. As with Nginx, set `'guzel_url' => true` in
`veri/yapilandirma.php` after installation.

## PHP's built-in server (development only)

```bash
php -S localhost:8000 yonlendirici.php
```

`yonlendirici.php` mimics the `.htaccess` rules. Do not use the built-in server in production.

## Production checklist

The dashboard shows a **Yayına hazırlık** (go-live) checklist. In addition:

1. **HTTPS** — enable SSL and force HTTPS; set the canonical site URL (Settings › Genel) to the `https://` address.
   HSTS is sent automatically when the site URL is HTTPS.
2. **Behind Cloudflare or a reverse proxy** — enable **Ters vekil / Cloudflare başlıklarına güven**
   (Settings › Gelişmiş) so visitor IPs are taken from proxy headers. Leave it off otherwise; trusting
   these headers without a proxy lets clients spoof their IP and dodge rate limits.
3. **Debug off** — `'hata_ayiklama' => false` in `veri/yapilandirma.php`.
4. **Check that protected paths return 403/404**, e.g. `https://example.com/veri/yapilandirma.php`,
   `https://example.com/sablonlar/blog.html`, `https://example.com/composer.json`.
5. **E-mail** — configure SMTP (Settings › E-posta) and send a test message; shared-host `mail()` often lands in spam.
6. **2FA** — enable TOTP for every administrator (Profil).
7. **Full-page cache** — on by default after installation (Settings › Performans).
8. **Search engines** — make sure "Arama motorlarını engelle" (discourage search engines) is off when you go live.

## Backups

**Yönetim › Yedekleme** exports a database-independent JSON backup (content, revisions, global fields,
settings, media metadata, forms, users, redirects, orders), optionally as a ZIP together with
`yuklemeler/`. The same file can be restored on SQLite or MySQL, which is also how you migrate between the two.
Backups contain password hashes and stored secrets (SMTP, payment, AI keys); keep them private.

For file-level backups copy `veri/` (config + SQLite database), `yuklemeler/`, `sablonlar/` and `tema/`.

## Updating

**Yönetim › Araçlar › Güncelleme** (one-click updater):

1. Reads the latest GitHub release of the repository in `BZ_REPO` (`bozkurt/boot.php`) or
   `guncelleme_deposu` in `veri/yapilandirma.php`. The repository cannot be changed from the panel, so a
   hijacked admin account cannot point the updater at malicious code.
2. Downloads the release ZIP and its `.sha256` file (GitHub hosts only) and aborts unless the checksum matches.
3. Saves a full JSON backup to `veri/yedekler/` first.
4. Extracts the files with zip-slip protection, **never overwriting** `veri/`, `yuklemeler/`, `sablonlar/`,
   `tema/`, `.htaccess` or `web.config`.
5. Clears caches and OPcache; database migrations run automatically on the next request.

> Releases are produced by `.github/workflows/yayin.yml` when a maintainer pushes a `v*` tag; the updater
> only offers versions newer than the installed `BZ_VERSION`.

**Manual update**: upload the new files over the old ones, keeping `veri/`, `yuklemeler/`, `sablonlar/` and
`tema/` (and your edited `.htaccess`/`web.config`).

## Moving from WordPress

**Araçlar › WordPress içe aktar** imports a WordPress export file (WXR, up to 50 MB) into a multi-entry
template. You choose the post types (default `post`) and which fields receive the content, excerpt and
category; published, draft and scheduled items are imported with their dates, and 301 redirects from the
old URLs can be created. External XML entities and network access are disabled while parsing.

## Static export

**Araçlar › Statik dışa aktar** produces a ZIP of the rendered site for static hosting (forms, search,
API and other dynamic features need the PHP site).
