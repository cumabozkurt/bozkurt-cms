# Architecture

BOZKURT CMS is a dependency-free PHP 8.1+ application. All classes live in `bozkurt/src/` under the
`Bozkurt` namespace and are loaded by a small autoloader in `bozkurt/boot.php`. Identifiers are in Turkish;
a glossary is at the end of this page.

## Directory layout

```
index.php            Front-end entry → App::boot() → Site::run()
yonetim/index.php    Admin entry → Installer (first run) or Admin::run()
yonlendirici.php     Router for `php -S` (development only, blocked on the web)
bozkurt/
  boot.php           BZ_VERSION, BZ_REPO, path constants, autoloader, App::boot()
  src/               Core classes (see below) + yardimcilar.php (global helper functions)
  gorunumler/        Admin panel views (plain PHP templates)
  lang/              tr.php, en.php (system strings), panel-en.php, panel-en-js.php (panel translation)
sablonlar/           Site templates (*.html), parcalar/ (partials), diller/*.json (string dictionaries)
tema/                Public assets for templates (stil.css)
yuklemeler/          Media uploads (YYYY/MM/…); script execution disabled by server config
veri/                Runtime data — never web-accessible
  yapilandirma.php   Config written by the installer
  bozkurt-*.sqlite   SQLite database (when SQLite is used)
  onbellek/          derlenmis/ (compiled templates), sayfalar/ (page cache), hiz/ (API rate limits), sablonlar.json
  oturumlar/         PHP sessions
  yedekler/          Automatic backup before each update
testler/             Test suites + PHPStan bootstrap
bin/depo-ayarla.php  CLI: change the GitHub repository name in docs/badges/updater
```

## Core classes

| Class | Responsibility |
|---|---|
| `App` | Boot, config, settings cache, base-path detection, languages, DB access, schema upgrades. |
| `Db` | Thin PDO layer for SQLite and MySQL/MariaDB; creates and migrates tables. |
| `Site` | Front-end router: system routes, maintenance mode, `.md` versions, page cache, template rendering, 404 + redirects, admin bar. |
| `Template` | Scans templates for field definitions, lints them and compiles `<bz:*>` tags to PHP. |
| `Runtime` | Executes compiled templates: variable lookup, filters, conditions, lists, forms, SEO/JSON-LD output. |
| `Content` | Content model: templates, entries, translations, revisions, global fields, validation, public/API views. |
| `Admin` | Admin panel controller (all panel pages and actions). |
| `Auth`, `Totp` | Login, sessions, roles/permissions, lockout, TOTP 2FA. |
| `Sanitizer`, `Markdown` | Allow-list HTML sanitizer; Markdown → HTML. |
| `Media` | Uploads, MIME checks, resizing, WebP thumbnails, EXIF stripping. |
| `Forms`, `Mailer`, `Sms` | Form handling and spam protection; SMTP/`mail()` e-mail; Netgsm SMS. |
| `Feeds` | sitemap.xml, robots.txt, RSS, llms.txt / llms-full.txt, Markdown page versions. |
| `Api`, `Mcp`, `Tokens`, `Webhook` | REST API, MCP server, API keys, signed webhooks. |
| `Ai` | OpenAI-compatible AI assistant calls. |
| `Payment` | PayTR and iyzico checkout and callbacks, orders. |
| `Redirects`, `Cache`, `Backup`, `Tools` | 301 redirects + 404 log; full-page cache; JSON/ZIP backup and restore; updater, WordPress import, static export. |
| `Validate`, `Str`, `Lang` | Turkish validators (TCKN, VKN, IBAN, provinces, holidays); Turkish-aware string helpers; translations. |
| `Installer`, `Sample` | Web installer; sample content. |

## Request lifecycle (front end)

```
index.php
 └─ App::boot()                 load veri/yapilandirma.php, timezone, base path
 └─ Site::run()
     ├─ not installed?          → redirect to /yonetim/ (installer)
     ├─ App::ensureSchema()     run migrations when the stored schema version differs
     ├─ security headers        nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS on HTTPS
     ├─ canonicalRedirect()     301 for /index.php, trailing slashes, upper-case paths
     ├─ detectLang()            strip /en/ … prefix, set App::$lang
     ├─ system routes           sitemap.xml · robots.txt · llms(-full).txt · rss.xml · {template}/rss.xml
     │                          api/… (Api) · mcp (Mcp) · odeme/… (Payment)
     ├─ maintenance mode        503 for visitors when enabled
     ├─ *.md                    Markdown version of a page
     ├─ page cache              serve cached HTML (anonymous GET, whitelisted query params only)
     ├─ resolve()               template + optional slug → compiled template → Runtime → HTML
     ├─ 404                     Redirects::handle() (301 if a redirect matches) → log 404 → 404 template
     └─ logged in?              inject the admin bar (inline editing); otherwise store in page cache
```

URL mapping: `/` → `sablonlar/ana-sayfa.html` (or `index.html`), `/hakkimizda` → single page,
`/blog` → list view of a multi-entry template, `/blog/my-post` → single entry. Deeper paths return 404.
Without pretty URLs the path is passed as `index.php?yol=…`.

## Template pipeline

1. **Scan** — `Template::scan()` reads `<bz:sablon>`, `<bz:alan>`, `<bz:tekrar>`, `<bz:bloklar>` and
   `<bz:genel>` tags to build the field definitions shown in the panel. Results are cached in
   `veri/onbellek/sablonlar.json` and refreshed when a template file changes.
2. **Compile** — `Template::compile()` removes any raw PHP from the source, then turns tags and
   `{{ }}` / `{{{ }}}` expressions into PHP that calls `Runtime` (`$R`). Output goes to
   `veri/onbellek/derlenmis/` and is recompiled when the source is newer.
3. **Render** — the compiled file is included with a `Runtime` instance holding the page data,
   site settings, global fields and request info.
4. **Lint** — `Template::lint()` reports unclosed or misplaced tags, unknown tags/field types and invalid
   field names; shown in the panel's template view and run over all bundled templates by the unit tests.

## Data model

| Table | Contents |
|---|---|
| `bz_icerik` | Content entries: template, language, translation group, title, slug, status, publish date, field values (`veri`, JSON), SEO data (`seo`, JSON), order, author. |
| `bz_surumler` | Revisions (last 25 per entry). |
| `bz_genel` | Global field values (per language). |
| `bz_ayarlar` | Settings (key/value). |
| `bz_medya` | Media library metadata. |
| `bz_formlar` | Form submissions. |
| `bz_kullanicilar` | Users, roles, password hashes, TOTP secrets, panel language. |
| `bz_giris_denemeleri` | Login attempts (lockout). |
| `bz_gunluk` | Activity log. |
| `bz_yonlendirmeler`, `bz_404` | Redirects and the 404 log. |
| `bz_tokenlar` | API keys (stored as SHA-256 hashes) with scope `oku` (read) or `yaz` (write). |
| `bz_sifre_sifirlama` | Password reset tokens. |
| `bz_siparisler` | Orders from PayTR/iyzico. |

Field values are stored as JSON, so adding a field to a template needs no migration. Backups export these
tables as database-independent JSON, which is also how a site moves between SQLite and MySQL.

## Caching

- **Compiled templates** — `veri/onbellek/derlenmis/`, invalidated by file modification time.
- **Full-page cache** — `veri/onbellek/sayfalar/`, only for anonymous GET requests whose query string
  contains nothing but `sayfa`, `kategori`, `yil`, `ay` (prevents cache-filling attacks). Cleared
  automatically whenever content or settings are saved. Responses carry `X-Bozkurt-Onbellek: HIT`.

## Glossary

| Turkish | English |
|---|---|
| sablon / sablonlar | template(s) |
| alan, genel alan | field, global field |
| tekrar, bloklar, blok | repeater, block editor, block |
| liste, oge, satir | list, list item, repeater row |
| eger / yoksa-eger / degilse | if / else-if / else |
| yoksa (inside a list) | empty-state ("if no items") |
| dahil | include |
| süzgeç | filter |
| coklu / tekil | multi-entry / single (page or entry) |
| taslak / yayinda | draft / published |
| yonetim, yonetici | administration, administrator |
| veri, yuklemeler, onbellek | data, uploads, cache |
| ayarlar | settings |
| evet / hayir | yes / no |
