<?php
declare(strict_types=1);

namespace Bozkurt;

/** 301/302 yönlendirme yöneticisi ve 404 günlüğü (eski siteden taşımada SEO kaybını önler). */
final class Redirects
{
    public static function normalize(string $path): string
    {
        $path = (string) parse_url($path, PHP_URL_PATH);
        $base = App::$basePath;
        if (str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return trim(mb_strtolower(rawurldecode($path)), '/');
    }

    public static function add(string $from, string $to, int $code = 301): void
    {
        $from = self::normalize($from);
        if ($from === '' || $from === self::normalize($to)) {
            return;
        }
        App::db()->upsert('bz_yonlendirmeler', ['kaynak' => $from, 'hedef' => mb_substr($to, 0, 500), 'kod' => in_array($code, [301, 302, 307, 308, 410], true) ? $code : 301, 'tarih' => bz_now()], 'kaynak');
    }

    /** Eşleşen yönlendirme varsa uygular ve çıkar. */
    public static function handle(string $path): void
    {
        $key = self::normalize($path);
        $r = App::db()->one('SELECT * FROM bz_yonlendirmeler WHERE kaynak = ?', [$key]);
        if (!$r) {
            return;
        }
        App::db()->q('UPDATE bz_yonlendirmeler SET sayac = sayac + 1 WHERE id = ?', [$r['id']]);
        if ((int) $r['kod'] === 410) {
            http_response_code(410);
            exit('<!doctype html><meta charset="utf-8"><title>410</title><h1>Bu sayfa kalıcı olarak kaldırıldı.</h1>');
        }
        $to = $r['hedef'];
        if (!preg_match('#^https?://#', $to)) {
            $to = rtrim(App::$basePath, '/') . '/' . ltrim($to, '/');
        }
        bz_redirect($to, (int) $r['kod']);
    }

    public static function log404(string $path): void
    {
        $path = mb_substr(self::normalize($path), 0, 255);
        if ($path === '' || preg_match('#\.(php|asp|aspx|env|git|sql|bak)$|wp-(admin|login|content)|xmlrpc#i', $path)) {
            return; // tarayıcı botlarının gürültüsünü kaydetme
        }
        $db = App::db();
        if ((int) $db->val('SELECT COUNT(*) FROM bz_404') > 2000) {
            $db->q('DELETE FROM bz_404 WHERE sayac < 2');
        }
        $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
        if ($db->val('SELECT COUNT(*) FROM bz_404 WHERE yol = ?', [$path])) {
            $db->q('UPDATE bz_404 SET sayac = sayac + 1, son = ?, yonlendiren = ? WHERE yol = ?', [bz_now(), $ref, $path]);
        } else {
            $db->insert('bz_404', ['yol' => $path, 'yonlendiren' => $ref, 'sayac' => 1, 'son' => bz_now()]);
        }
    }
}
