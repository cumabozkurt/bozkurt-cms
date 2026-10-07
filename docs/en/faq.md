# FAQ

### Does BOZKURT need Composer, Node.js or a build step?
No. It is plain PHP 8.1+ with no third-party runtime dependencies. Upload the files and run the web installer.
Node.js is only used in CI to syntax-check the panel JavaScript.

### Is it only for Turkish sites?
The panel defaults to Turkish and the template tags are Turkish words, but sites can be multilingual
(`/en/`, `/de/` … prefixes, `hreflang`, RTL support) and each admin user can switch the panel to English
under **Profil**. Turkey-specific features (KVKK, PayTR/iyzico, Netgsm, TCKN/VKN fields) are optional.

### SQLite or MySQL?
SQLite needs no setup and is fine for typical company sites, blogs and portfolios. Choose MySQL/MariaDB if
your host recommends it or you expect heavy concurrent writes. You can switch later by exporting a JSON
backup and restoring it on a fresh installation using the other driver.

### I get a blank page or a 500 error.
Check `veri/hatalar.log`. Temporarily set `'hata_ayiklama' => true` in `veri/yapilandirma.php` to see the
error, and switch it off again afterwards. The panel's template view also lints templates for common mistakes.

### Pretty URLs do not work (I get `index.php?yol=…`).
The installer enables pretty URLs only when it detects Apache/LiteSpeed with `.htaccess` (or `php -S`).
On Nginx/IIS configure rewrites (see [deployment.md](deployment.md)) and then set `'guzel_url' => true`
in `veri/yapilandirma.php`.

### "Too many failed attempts" — I am locked out.
Wait 15 minutes. In an emergency, empty the `bz_giris_denemeleri` table in the database.

### I forgot my password.
Use **Şifremi unuttum** (Forgot password) on the login page; SMTP must be configured. Otherwise write the
output of PHP's `password_hash('new-password', PASSWORD_DEFAULT)` into `bz_kullanicilar.sifre` for your user
(for example with phpMyAdmin or the `sqlite3` CLI).

### How do I re-install?
Back up first, then delete `veri/yapilandirma.php`, `veri/kurulum.kilit` and the `veri/*.sqlite` file.
The installer deliberately refuses to run while those exist.

### I use Cloudflare. Anything to configure?
Enable **Ters vekil / Cloudflare başlıklarına güven** (Settings › Gelişmiş) so real visitor IPs are used, and
add cache-bypass rules for `/yonetim/*`, `/api/*`, `/mcp` and `/odeme/*`.

### Where are my files and data?
Content and settings live in the database (`veri/*.sqlite` or MySQL); uploads in `yuklemeler/`; templates in
`sablonlar/` and their assets in `tema/`. See [architecture.md](architecture.md).

### Can I use it headless with Next.js, Nuxt or a mobile app?
Yes — use the read/write REST API under `/api/v1`. See [api.md](api.md).

### Can AI assistants access my content?
Only if you allow it: `llms.txt` and `.md` page versions are public read-only outputs (on by default and can
be disabled); the MCP server must be enabled explicitly and requires an API key. MCP write tools only
create drafts.

### Where do I download a release? How does the one-click updater work?
Download `bozkurt-cms-x.y.z.zip` from the [latest release](https://github.com/cumabozkurt/bozkurt-cms/releases/latest). The updater (Araçlar › Güncelleme) checks the
same GitHub releases, verifies the `.sha256` checksum and installs newer versions; releases are built
automatically when a maintainer pushes a `v*` tag.

### Is BOZKURT based on CouchCMS code?
No. It reuses the *idea* of making HTML templates editable with tags, but contains no CouchCMS code and is
MIT-licensed.
