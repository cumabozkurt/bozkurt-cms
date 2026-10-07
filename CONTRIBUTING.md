# Katkı Rehberi / Contributing

**Türkçe** · [English](#english)

Katkılarınızı bekliyoruz! 🐺 Hata bildirimi, belge düzeltmesi, çeviri, test ve kod katkılarının hepsi değerlidir.
Katılarak [Davranış Kuralları](CODE_OF_CONDUCT.md)'nı kabul etmiş olursunuz.

## Geliştirme ortamı

```bash
git clone https://github.com/cumabozkurt/bozkurt-cms.git && cd bozkurt-cms
php -S localhost:8000 yonlendirici.php     # http://localhost:8000/yonetim/
```

PHP 8.1+ ve `pdo_sqlite`, `mbstring`, `gd`, `zip`, `dom`, `fileinfo` eklentileri yeterlidir.

## Kurallar

1. `ozellik/kisa-ad` veya `duzeltme/kisa-ad` adında bir dal açın.
2. PSR-12, `declare(strict_types=1);`, **PHP 8.1 uyumlu** kod. Composer/npm bağımlılığı eklemeyin; paylaşımlı hosting uyumluluğu temel ilkemizdir.
3. Kullanıcıya görünen panel metinleri Türkçe yazılır; İngilizce karşılığı `bozkurt/lang/panel-en.php` (JS için `panel-en-js.php`) dosyasına eklenir.
4. Şablonlardaki iç bağlantılarda `{{ site.onek }}` kullanın (çok dilli sitelerde dil önekini korur).
5. Hata düzeltmeleri, düzeltmeden önce başarısız olan bir testle gelir (`testler/birim.php` veya `testler/kapsamli.php`).
6. Yeni özellikte `README.md` / `README.tr.md`, ilgili `docs/` sayfaları (`docs/SABLON-REHBERI.md`, `docs/en/…`) ve `CHANGELOG.md` (“Yayımlanmamış” bölümü) güncellenir.
7. Dosya biçimi için `.editorconfig` kullanılır (UTF-8, LF, 4 boşluk; YAML 2 boşluk).

## Göndermeden önce

```bash
find . -name "*.php" -not -path "./.git/*" -print0 | xargs -0 -n1 php -l
php testler/birim.php
php testler/duman.php
php testler/kapsamli.php      # MySQL için: BZ_TEST_MYSQL="127.0.0.1;3306;db;kullanici;sifre"
php testler/tarama.php
phpstan analyse -c phpstan.neon.dist
node --check yonetim/assets/yonetim.js
```

> MySQL modunda `kapsamli.php` verilen veritabanındaki **tüm tabloları siler**; yalnızca teste ayrılmış boş bir veritabanı kullanın.

CI aynı denetimleri PHP 8.1–8.4 ve MySQL 8 üzerinde çalıştırır. Ayrıntılar: [docs/en/testing.md](docs/en/testing.md).

## Sürüm yayınlama (bakımcılar)

1. `bozkurt/boot.php` içindeki `BZ_VERSION` ve `CHANGELOG.md` güncellenir (“Yayımlanmamış” başlığı `## x.y.z — YYYY-AA-GG` olur).
2. `git tag vX.Y.Z && git push origin vX.Y.Z`
3. **Yayın** iş akışı testleri çalıştırır, `bozkurt-cms-X.Y.Z.zip` ve `.sha256` dosyasını üretip GitHub Release'e ekler. Paneldeki tek tıkla güncelleme bu dosyaları kullanır.

Güvenlik açıkları için issue açmayın; [SECURITY.md](SECURITY.md)'deki yolu izleyin.

---

## English

Contributions are welcome! 🐺 Bug reports, documentation fixes, translations, tests and code are all valuable.
By participating you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

### Development setup

```bash
git clone https://github.com/cumabozkurt/bozkurt-cms.git && cd bozkurt-cms
php -S localhost:8000 yonlendirici.php     # http://localhost:8000/yonetim/
```

PHP 8.1+ with `pdo_sqlite`, `mbstring`, `gd`, `zip`, `dom` and `fileinfo` is all you need.

### Guidelines

1. Branch names: `ozellik/short-name` (feature) or `duzeltme/short-name` (fix).
2. PSR-12, `declare(strict_types=1);`, **PHP 8.1-compatible** code. Do not add Composer/npm dependencies — shared-hosting compatibility is a core principle.
3. User-facing panel strings are written in Turkish; add the English translation to `bozkurt/lang/panel-en.php` (`panel-en-js.php` for JavaScript).
4. Use `{{ site.onek }}` for internal links in templates (keeps the language prefix on multilingual sites).
5. Bug fixes come with a test that fails before the fix (`testler/birim.php` or `testler/kapsamli.php`).
6. New features update `README.md` / `README.tr.md`, the relevant `docs/` pages (`docs/SABLON-REHBERI.md`, `docs/en/…`) and the "Unreleased" section of `CHANGELOG.md`.
7. Formatting follows `.editorconfig` (UTF-8, LF, 4 spaces; 2 for YAML).
8. Identifiers and comments in the codebase are Turkish; please keep that style for consistency.

### Before you open a pull request

Run the commands listed above (syntax check, the four test suites, PHPStan, `node --check`). In MySQL mode
`kapsamli.php` **drops every table** in the target database — use an empty, dedicated test database.
CI runs the same checks on PHP 8.1–8.4 and MySQL 8. Details: [docs/en/testing.md](docs/en/testing.md).

### Releasing (maintainers)

1. Update `BZ_VERSION` in `bozkurt/boot.php` and `CHANGELOG.md` (turn "Unreleased" into `## x.y.z — YYYY-MM-DD`).
2. `git tag vX.Y.Z && git push origin vX.Y.Z`
3. The **Yayın** (release) workflow runs the tests, builds `bozkurt-cms-X.Y.Z.zip` plus `.sha256` and attaches them to a GitHub Release, which the one-click updater uses.

Please do not report security vulnerabilities in public issues — follow [SECURITY.md](SECURITY.md).
