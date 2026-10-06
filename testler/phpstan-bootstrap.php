<?php
// PHPStan için sabitler
const BZ_VERSION = '1.0.0';
const BZ_REPO = 'cumabozkurt/bozkurt-cms';
define('BZ_ROOT', dirname(__DIR__));
define('BZ_CORE', BZ_ROOT . '/bozkurt');
define('BZ_DATA', BZ_ROOT . '/veri');
define('BZ_UPLOADS', BZ_ROOT . '/yuklemeler');
define('BZ_TEMPLATES', BZ_ROOT . '/sablonlar');
define('BZ_CACHE', BZ_DATA . '/onbellek');
require BZ_CORE . '/src/yardimcilar.php';
spl_autoload_register(static function (string $c): void {
    if (str_starts_with($c, 'Bozkurt\\')) {
        $f = BZ_CORE . '/src/' . substr($c, 8) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});
