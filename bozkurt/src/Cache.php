<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Tam sayfa önbelleği: ziyaretçiler için hazır HTML'i diskten sunar (paylaşımlı hostingde ciddi hız kazancı).
 * Yönetimde herhangi bir kayıt yapıldığında otomatik temizlenir.
 */
final class Cache
{
    private static function dir(): string
    {
        return BZ_CACHE . '/sayfalar';
    }

    public static function enabled(): bool
    {
        $host = (string) parse_url(App::siteUrl(), PHP_URL_HOST);
        return App::setting('onbellek', '1') === '1'
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            && !isset($_COOKIE['BZOTURUM'])
            && strcasecmp((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')), $host) === 0;
    }

    public static function key(): string
    {
        return md5(($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/') . Template::signature());
    }

    public static function get(): ?string
    {
        $f = self::dir() . '/' . self::key() . '.html';
        $ttl = (int) App::setting('onbellek_sure', '3600');
        if (is_file($f) && filemtime($f) > time() - $ttl) {
            return (string) file_get_contents($f);
        }
        return null;
    }

    public static function put(string $html): void
    {
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0755, true);
        }
        // Disk doldurma saldırısına karşı: aşırı uzun adresleri yazma, dosya sayısını sınırla
        if (strlen((string) ($_SERVER['REQUEST_URI'] ?? '')) > 300 || (int) ($_GET['sayfa'] ?? 1) > 200) {
            return;
        }
        if (random_int(1, 100) === 1 && count(glob(self::dir() . '/*.html') ?: []) > 5000) {
            self::clear();
        }
        @file_put_contents(self::dir() . '/' . self::key() . '.html', $html, LOCK_EX);
    }

    public static function clear(): int
    {
        $n = 0;
        foreach (glob(self::dir() . '/*.html') ?: [] as $f) {
            @unlink($f);
            $n++;
        }
        return $n;
    }

    public static function clearAll(): void
    {
        self::clear();
        foreach (array_merge(glob(BZ_CACHE . '/derlenmis/*.php') ?: [], [BZ_CACHE . '/sablonlar.json']) as $f) {
            @unlink($f);
        }
    }
}
