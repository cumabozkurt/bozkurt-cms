<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Tek ekranlık web kurulum sihirbazı. FTP ile yükle → /yonetim/ aç → formu doldur → bitti.
 */
final class Installer
{
    public static function requirements(): array
    {
        $writable = fn($d) => is_dir($d) && is_writable($d);
        return [
            ['PHP 8.1 veya üzeri', PHP_VERSION_ID >= 80100, PHP_VERSION, true],
            ['PDO SQLite', extension_loaded('pdo_sqlite'), extension_loaded('pdo_sqlite') ? 'var' : 'yok', false],
            ['PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'var' : 'yok', false],
            ['mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'var' : 'yok', true],
            ['GD (resim işleme)', extension_loaded('gd'), extension_loaded('gd') ? (function_exists('imagewebp') ? 'WebP destekli' : 'var') : 'yok', false],
            ['fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'var' : 'yok', true],
            ['DOM (HTML temizleyici)', extension_loaded('dom'), extension_loaded('dom') ? 'var' : 'yok', true],
            ['ZipArchive (yedekleme)', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'var' : 'yok', false],
            ['veri/ yazılabilir', $writable(BZ_DATA), $writable(BZ_DATA) ? 'evet' : 'hayır', true],
            ['yuklemeler/ yazılabilir', $writable(BZ_UPLOADS), $writable(BZ_UPLOADS) ? 'evet' : 'hayır', true],
            ['Yükleme sınırı', true, ini_get('upload_max_filesize') . ' / bellek ' . ini_get('memory_limit'), false],
        ];
    }

    public static function run(): void
    {
        bz_session();
        $checks = self::requirements();
        $blocking = array_filter($checks, fn($c) => $c[3] && !$c[1]);
        // Kurulum kilidi: yapılandırma silinmiş ama veritabanı duruyorsa yabancının yeniden kurmasına izin verme
        if (glob(BZ_DATA . '/*.sqlite') || is_file(BZ_DATA . '/kurulum.kilit')) {
            $blocking[] = ['Mevcut kurulum algılandı (veri/ içinde veritabanı veya kurulum.kilit). Yeniden kurmak için bu dosyaları FTP ile kaldırın.', false, 'kilitli', true];
        }
        if (!extension_loaded('pdo_sqlite') && !extension_loaded('pdo_mysql')) {
            $blocking[] = ['PDO SQLite veya PDO MySQL', false, 'yok', true];
        }
        $errors = [];
        $v = [
            'site_adi' => 'Sitem', 'ad' => '', 'eposta' => '', 'surucu' => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql',
            'db_sunucu' => 'localhost', 'db_port' => '3306', 'db_ad' => '', 'db_kullanici' => '', 'ornek' => '1',
        ];
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$blocking) {
            if (!bz_csrf_check()) {
                $errors[] = 'Güvenlik belirteci geçersiz, sayfayı yenileyin.';
            }
            foreach ($v as $k => $_) {
                $v[$k] = trim((string) ($_POST[$k] ?? ($k === 'ornek' ? '' : $v[$k])));
            }
            $pass = (string) ($_POST['sifre'] ?? '');
            if ($v['ad'] === '') {
                $errors[] = 'Adınızı girin.';
            }
            if (!filter_var($v['eposta'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Geçerli bir e-posta girin.';
            }
            if (strlen($pass) < 10 || in_array(strtolower($pass), ['1234567890', 'qwertyuiop', 'password12', 'sifre12345', '12345678910'], true)) {
                $errors[] = 'Şifre en az 10 karakter olmalı.';
            }
            $cfg = [
                'kuruldu' => true,
                'anahtar' => bin2hex(random_bytes(32)),
                'saat_dilimi' => 'Europe/Istanbul',
                'dil' => 'tr',
                'guzel_url' => self::rewriteWorks(),
                'hata_ayiklama' => false,
                'db' => $v['surucu'] === 'mysql'
                    ? ['surucu' => 'mysql', 'sunucu' => $v['db_sunucu'], 'port' => (int) $v['db_port'], 'ad' => $v['db_ad'], 'kullanici' => $v['db_kullanici'], 'sifre' => (string) ($_POST['db_sifre'] ?? '')]
                    : ['surucu' => 'sqlite', 'dosya' => BZ_DATA . '/bozkurt-' . bin2hex(random_bytes(6)) . '.sqlite'],
            ];
            if (!$errors) {
                try {
                    $db = new Db($cfg);
                    App::setDb($db);
                    $db->migrate();
                    if ($db->val('SELECT COUNT(*) FROM bz_kullanicilar')) {
                        throw new \RuntimeException('Bu veritabanında zaten bir BOZKURT kurulumu var.');
                    }
                    $db->insert('bz_kullanicilar', [
                        'ad' => $v['ad'], 'eposta' => Str::lower($v['eposta']), 'sifre' => password_hash($pass, PASSWORD_DEFAULT),
                        'rol' => 'yonetici', 'olusturma' => bz_now(),
                    ]);
                    App::$config = $cfg;
                    App::saveSettings([
                        'site_adi' => $v['site_adi'], 'bildirim_eposta' => $v['eposta'], 'onbellek' => '1', 'cerez_bandi' => '1',
                        'ip_anonim' => '1', 'kvkk_sayfasi' => 'kvkk', 'site_aciklama' => $v['site_adi'] . ' resmi web sitesi',
                        'site_url' => App::requestOrigin() . rtrim(App::$basePath, '/'), 'sema' => App::SCHEMA, 'diller' => 'tr',
                        'yz_botlari' => 'sadece_arama', 'yz_llms' => '1', 'yz_markdown' => '1',
                    ]);
                    if ($v['ornek'] === '1') {
                        Sample::seed();
                    }
                    foreach (['onbellek', 'oturumlar'] as $d) {
                        @mkdir(BZ_DATA . '/' . $d, 0755, true);
                    }
                    $php = "<?php\n// BOZKURT CMS yapılandırması — " . bz_now() . "\nreturn " . var_export($cfg, true) . ";\n";
                    if (file_put_contents(BZ_DATA . '/yapilandirma.php', $php, LOCK_EX) === false) {
                        throw new \RuntimeException('veri/yapilandirma.php yazılamadı. Klasör izinlerini kontrol edin.');
                    }
                    @chmod(BZ_DATA . '/yapilandirma.php', 0600);
                    @file_put_contents(BZ_DATA . '/kurulum.kilit', bz_now());
                    $u = $db->one('SELECT id FROM bz_kullanicilar LIMIT 1');
                    Auth::login((int) $u['id']);
                    bz_flash('Kurulum tamamlandı. BOZKURT CMS\'e hoş geldiniz! 🐺');
                    bz_redirect(App::adminUrl());
                } catch (\Throwable $e) {
                    $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
                }
            }
        }
        $vars = ['kontroller' => $checks, 'engeller' => $blocking, 'hatalar' => $errors, 'v' => $v];
        extract($vars);
        require BZ_CORE . '/gorunumler/kurulum.php';
    }

    /** .htaccess ile mod_rewrite çalışıyor mu? (LiteSpeed/Apache) */
    private static function rewriteWorks(): bool
    {
        $soft = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules(), true)) {
            return true;
        }
        if (stripos($soft, 'litespeed') !== false || stripos($soft, 'apache') !== false || isset($_SERVER['HTTP_X_BZ_REWRITE'])) {
            return is_file(BZ_ROOT . '/.htaccess');
        }
        return PHP_SAPI === 'cli-server'; // php -S yonlendirici.php ile geliştirme
    }
}
