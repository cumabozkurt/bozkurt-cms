# Testing

BOZKURT has four dependency-free test suites in `testler/` ("tests"). Each one copies the repository to a
temporary directory, so your local `veri/` data is never touched, and prints one line per check
(`✓` passed / `✗` failed) followed by a summary. A non-zero exit code means at least one check failed.

| Command | Suite | What it covers |
|---|---|---|
| `php testler/birim.php` | Unit (*birim*) | No database or server. HTML sanitizer (XSS vectors, iframe allow-list, control-character schemes), Markdown, template compiler and linter (including all bundled templates and the README example), runtime filters and conditions, KVKK consent markup, JSON-LD encoding, TCKN/VKN/IBAN/province/holiday validators, TOTP RFC 6238 vectors, Turkish string helpers, SSRF helper, redirects, form tokens. |
| `php testler/duman.php` | Smoke (*duman*) | Installs into a temp copy and visits the main pages. |
| `php testler/kapsamli.php` | End-to-end (*kapsamlı*) | Starts `php -S`, runs the installer and drives the site and panel over HTTP: content, blocks, slug redirects, multilingual, AI outputs, API and MCP, forms, inline editing, import/export, canonical URLs, security regressions and attacker scenarios. |
| `php testler/tarama.php` | Crawl (*tarama*) | Crawls the whole site in every language, validates every internal link and asset, basic HTML/SEO/accessibility rules, XML outputs, opens every panel screen in both panel languages, uploads a real image and round-trips a backup. |

Static analysis and syntax:

```bash
find . -name "*.php" -not -path "./.git/*" -print0 | xargs -0 -n1 php -l
phpstan analyse -c phpstan.neon.dist          # level 5, PHP 8.1 target
node --check yonetim/assets/yonetim.js
```

## Running the end-to-end suite on MySQL

```bash
BZ_TEST_MYSQL="127.0.0.1;3306;bztest;user;password" php testler/kapsamli.php
```

> **Warning:** in MySQL mode the suite **drops every table** in the given database before it starts.
> Only point it at an empty database created for testing.

## Requirements

PHP 8.1+ with `pdo_sqlite` (and `pdo_mysql` for MySQL runs), `mbstring`, `dom`, `fileinfo`, `gd`, `zip`,
`curl`. The end-to-end and crawl suites need a free local port (picked at random).

## Continuous integration

[`.github/workflows/denetim.yml`](../../.github/workflows/denetim.yml) ("audit") runs on every push and pull request:

| Job | Runs |
|---|---|
| `php` (matrix 8.1, 8.2, 8.3, 8.4) | Syntax check, PHPStan, unit, smoke, end-to-end (SQLite) and crawl suites. |
| `mysql` | End-to-end suite against a MySQL 8.0 service container. |
| `js` | `node --check` on the panel JavaScript. |

[`.github/workflows/yayin.yml`](../../.github/workflows/yayin.yml) ("release") runs only for `v*` tags: it
re-runs the tests, checks that the tag matches `BZ_VERSION`, and attaches `bozkurt-cms-x.y.z.zip` and its
`.sha256` checksum to a GitHub Release.

## Writing tests

- Pure logic → add a `k(condition, 'description')` check to the relevant `bolum()` ("section") in `testler/birim.php`.
- Behaviour visible over HTTP → add it to `testler/kapsamli.php`.
- A bug fix should come with a check that fails before the fix and passes after it.
