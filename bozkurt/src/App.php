<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Uygulama kapsayıcısı: yapılandırma, veritabanı, ayarlar, dil ve adresler.
 */
final class App
{
    public const SCHEMA = '1';
    public static array $config = [];
    private static ?Db $db = null;
    private static ?array $settings = null;
    public static string $basePath = '';
    /** Geçerli içerik dili (ör. "tr", "en") */
    public static string $lang = 'tr';

    public static function boot(): void
    {
        $file = BZ_DATA . '/yapilandirma.php';
        if (is_file($file)) {
            self::$config = require $file;
        }
        date_default_timezone_set(self::$config['saat_dilimi'] ?? 'Europe/Istanbul');
        self::$basePath = self::detectBasePath();

        if (!empty(self::$config['hata_ayiklama'])) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            if (is_dir(BZ_DATA)) {
                ini_set('error_log', BZ_DATA . '/hatalar.log');
            }
        }
    }

    public static function installed(): bool
    {
        return !empty(self::$config['kuruldu']);
    }

    /** Eski kurulumları sessizce yeni veritabanı şemasına yükseltir. */
    public static function ensureSchema(): void
    {
        if (self::installed() && self::setting('sema', '0') !== self::SCHEMA) {
            self::db()->migrate();
            self::saveSettings(['sema' => self::SCHEMA]);
        }
    }

    private static function detectBasePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(dirname($script), '/');
        if (str_ends_with($dir, '/yonetim')) {
            $dir = substr($dir, 0, -8);
        }
        return $dir . '/';
    }

    public static function db(): Db
    {
        if (self::$db === null) {
            self::$db = new Db(self::$config);
        }
        return self::$db;
    }

    public static function setDb(Db $db): void
    {
        self::$db = $db;
    }

    public static function setting(string $key, mixed $default = null): mixed
    {
        if (self::$db === null && !self::installed()) {
            return $default; // kurulum öncesi: veritabanı dosyası yanlışlıkla oluşturulmasın
        }
        if (self::$settings === null) {
            self::$settings = [];
            try {
                foreach (self::db()->all('SELECT anahtar, deger FROM bz_ayarlar') as $row) {
                    self::$settings[$row['anahtar']] = $row['deger'];
                }
            } catch (\Throwable) {
                // kurulum öncesi
            }
        }
        $v = self::$settings[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function saveSettings(array $values): void
    {
        $db = self::db();
        foreach ($values as $k => $v) {
            $db->upsert('bz_ayarlar', ['anahtar' => $k, 'deger' => (string) $v], 'anahtar');
        }
        self::$settings = null;
    }

    /* ------------------------------------------------------------ diller */

    /** Etkin içerik dilleri; ilki varsayılandır. Ayarlar > Diller: "tr,en,de" */
    public static function languages(): array
    {
        $list = array_values(array_unique(array_filter(array_map(
            fn($l) => strtolower(trim($l)),
            explode(',', (string) self::setting('diller', self::$config['dil'] ?? 'tr'))
        ), fn($l) => (bool) preg_match('/^[a-z]{2}$/', $l))));
        return $list ?: ['tr'];
    }

    public static function defaultLang(): string
    {
        return self::languages()[0];
    }

    public static function isMultilingual(): bool
    {
        return count(self::languages()) > 1;
    }

    public const LANG_NAMES = [
        'tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'ar' => 'العربية', 'ru' => 'Русский',
        'az' => 'Azərbaycanca', 'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands', 'fa' => 'فارسی', 'ka' => 'ქართული',
        'kk' => 'Қазақша', 'uz' => 'Oʻzbekcha', 'bg' => 'Български', 'el' => 'Ελληνικά', 'ja' => '日本語', 'zh' => '中文',
    ];

    public static function langName(string $l): string
    {
        return self::LANG_NAMES[$l] ?? strtoupper($l);
    }

    /* ------------------------------------------------------------ adresler */

    /**
     * Sitenin kanonik kök adresi. Kurulumda kaydedilir; Host başlığı zehirlenmesine karşı
     * e-posta, canonical ve sitemap'te her zaman bu kullanılır.
     */
    public static function siteUrl(): string
    {
        $fixed = (string) self::setting('site_url', '');
        if ($fixed !== '') {
            return rtrim($fixed, '/');
        }
        return self::requestOrigin() . rtrim(self::$basePath, '/');
    }

    public static function requestOrigin(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (self::trustProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    public static function isHttps(): bool
    {
        return str_starts_with(self::siteUrl(), 'https://');
    }

    /** Cloudflare / ters vekil başlıklarına yalnızca açıkça izin verildiyse güvenilir. */
    public static function trustProxy(): bool
    {
        return (string) self::setting('guvenilir_vekil', '0') === '1';
    }

    /** Dil öneki dahil site içi adres. $lang null ise geçerli dil. */
    public static function url(string $path = '', ?string $lang = null): string
    {
        $path = ltrim($path, '/');
        $lang ??= self::$lang;
        if ($lang !== self::defaultLang() && in_array($lang, self::languages(), true)) {
            $path = $lang . ($path !== '' ? '/' . $path : '');
        }
        if (!empty(self::$config['guzel_url']) || $path === '') {
            return self::$basePath . $path;
        }
        return self::$basePath . 'index.php?yol=' . $path;
    }

    public static function adminUrl(string $section = '', array $params = []): string
    {
        $q = $section !== '' ? ['s' => $section] + $params : $params;
        return self::$basePath . 'yonetim/' . ($q ? '?' . http_build_query($q) : '');
    }
}
