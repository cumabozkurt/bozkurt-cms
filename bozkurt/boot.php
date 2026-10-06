<?php
/**
 * BOZKURT CMS — Çekirdek önyükleyici
 * Türkçe öncelikli, paylaşımlı hosting dostu, tasarımcılar için açık kaynak CMS.
 * Lisans: MIT
 */
declare(strict_types=1);

const BZ_VERSION = '1.0.0';
/** GitHub deposu (tek tıkla güncelleme ve bağlantılar). Değiştirmek için: php bin/depo-ayarla.php kullanici/depo */
const BZ_REPO = 'cumabozkurt/bozkurt-cms';
define('BZ_ROOT', dirname(__DIR__));
define('BZ_CORE', __DIR__);
define('BZ_DATA', BZ_ROOT . '/veri');
define('BZ_UPLOADS', BZ_ROOT . '/yuklemeler');
define('BZ_TEMPLATES', BZ_ROOT . '/sablonlar');
define('BZ_CACHE', BZ_DATA . '/onbellek');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('BOZKURT CMS için PHP 8.1 veya üzeri gerekir. Hosting panelinizden PHP sürümünü yükseltin.');
}

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Bozkurt\\')) {
        $file = BZ_CORE . '/src/' . str_replace('\\', '/', substr($class, 8)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require BZ_CORE . '/src/yardimcilar.php';

mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

Bozkurt\App::boot();
