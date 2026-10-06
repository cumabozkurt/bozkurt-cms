<?php
/**
 * Yerel geliştirme için: php -S localhost:8000 yonlendirici.php
 * (Hosting'de gerekmez; orada .htaccess kullanılır.)
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$deny = '#^/(veri|bozkurt|sablonlar|testler|bin|docs)(/|$)#';
if (preg_match($deny, $path) || preg_match('#/\.(?!well-known/)#', $path) || preg_match('#^/yuklemeler/.*\.(php\d?|phtml|phar|html?|svg)$#i', $path)) {
    http_response_code(403);
    exit('Erişim engellendi');
}
if ($path !== '/' && is_file(__DIR__ . $path) && !str_ends_with($path, '.php')) {
    return false;
}
if (str_starts_with($path, '/yonetim')) {
    $_SERVER['SCRIPT_NAME'] = '/yonetim/index.php';
    require __DIR__ . '/yonetim/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
