<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * JSON API (headless: Next.js/Nuxt ön yüzleri, mobil uygulamalar, otomasyonlar)
 *   GET    /api/v1/sablonlar
 *   GET    /api/v1/genel
 *   GET    /api/v1/{sablon}?limit=&sayfa=&sirala=&yon=&q=&filtre=&dil=
 *   GET    /api/v1/{sablon}/{slug}
 *   POST   /api/v1/{sablon}                 (yazma anahtarı)  {"baslik":..,"durum":"taslak|yayinda","alanlar":{..}}
 *   PATCH  /api/v1/{sablon}/{slug}          (yazma anahtarı)
 *   DELETE /api/v1/{sablon}/{slug}          (yazma anahtarı)
 * Okuma: Ayarlar'dan herkese açık yapılabilir veya anahtar ister. Yazma: her zaman "yaz" kapsamlı anahtar.
 */
final class Api
{
    public static function handle(string $path): never
    {
        $cors = (string) App::setting('api_cors', '');
        if ($cors !== '') {
            header('Access-Control-Allow-Origin: ' . ($cors === '*' ? '*' : preg_replace('/[^a-z0-9:\/.\-]/i', '', $cors)));
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        }
        header('Cache-Control: no-store');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        $token = Tokens::fromRequest();
        $write = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($write) {
            if (!$token || $token['kapsam'] !== 'yaz') {
                bz_json(['hata' => 'Yazma işlemi için "yaz" kapsamlı API anahtarı gerekir.'], 401);
            }
        } elseif (App::setting('api_acik') !== '1' && !$token) {
            bz_json(['hata' => 'API kapalı ya da anahtar gerekli. Yönetim › Ayarlar › API.'], 403);
        }
        self::rateLimit($token);

        $parts = array_values(array_filter(explode('/', $path), fn($p) => $p !== ''));
        if (($parts[0] ?? '') !== 'v1') {
            bz_json(['hata' => 'Bilinmeyen sürüm. /api/v1/ kullanın.'], 404);
        }
        if (isset($_GET['dil']) && in_array($_GET['dil'], App::languages(), true)) {
            App::$lang = (string) $_GET['dil'];
        }
        $res = $parts[1] ?? 'sablonlar';
        if ($res === 'sablonlar') {
            $out = [];
            foreach (Content::templates() as $n => $t) {
                $out[] = ['ad' => $n, 'baslik' => $t['meta']['baslik'], 'coklu' => $t['meta']['coklu'],
                    'alanlar' => array_map(fn($d) => ['tur' => $d['tur'], 'etiket' => $d['etiket'], 'zorunlu' => $d['zorunlu']], array_filter($t['alanlar'], fn($d) => !empty($d['api'])))];
            }
            bz_json(['veri' => $out, 'diller' => App::languages()]);
        }
        if ($res === 'genel') {
            $out = [];
            foreach (Content::globalDefs() as $n => $d) {
                $out[$n] = Content::presentValue($d, Content::globalValue($n) ?? $d['varsayilan']);
            }
            bz_json(['veri' => $out]);
        }
        $tpl = Content::template($res);
        if (!$tpl) {
            bz_json(['hata' => 'Şablon bulunamadı.'], 404);
        }
        $slug = $parts[2] ?? null;

        if ($write) {
            self::write($method, $res, $tpl, $slug, $token);
        }
        if (!$tpl['meta']['coklu']) {
            bz_json(['veri' => Content::publicView(Content::single($res))]);
        }
        if ($slug !== null) {
            $row = Content::findBySlug($res, $slug);
            $row ? bz_json(['veri' => Content::publicView($row)]) : bz_json(['hata' => 'İçerik bulunamadı.'], 404);
        }
        $r = Content::query([
            'sablon' => $res, 'limit' => min(100, max(1, (int) ($_GET['limit'] ?? 20))), 'sayfa' => (int) ($_GET['sayfa'] ?? 1),
            'sirala' => preg_replace('/[^a-z0-9_]/', '', (string) ($_GET['sirala'] ?? 'tarih')), 'yon' => (string) ($_GET['yon'] ?? 'azalan'),
            'arama' => mb_substr((string) ($_GET['q'] ?? ''), 0, 100), 'filtre' => mb_substr((string) ($_GET['filtre'] ?? ''), 0, 300),
        ]);
        $rows = array_map(fn($it) => Content::publicView(Content::find((int) $it['id']) ?? []), $r['ogeler']);
        bz_json(['veri' => $rows, 'meta' => ['toplam' => $r['toplam'], 'sayfa' => $r['sayfa'], 'sayfa_sayisi' => $r['sayfa_sayisi'], 'dil' => App::$lang]]);
    }

    private static function write(string $method, string $name, array $tpl, ?string $slug, array $token): never
    {
        $existing = null;
        if ($slug !== null) {
            $existing = Content::findBySlug($name, $slug, false);
            if (!$existing) {
                bz_json(['hata' => 'İçerik bulunamadı.'], 404);
            }
        } elseif (!$tpl['meta']['coklu']) {
            $existing = Content::single($name);
        }
        if ($method === 'DELETE') {
            if (!$existing || $existing['slug'] === '') {
                bz_json(['hata' => 'Tekil sayfalar silinemez.'], 400);
            }
            Content::deleteEntry($existing);
            bz_json(['ok' => true]);
        }
        if ($method === 'POST' && $existing && $tpl['meta']['coklu']) {
            bz_json(['hata' => 'Var olan içerik için PATCH kullanın.'], 405);
        }
        $raw = file_get_contents('php://input', false, null, 0, 2_000_000);
        $in = json_decode((string) $raw, true);
        if (!is_array($in)) {
            bz_json(['hata' => 'Gövde geçerli JSON olmalı.'], 400);
        }
        $fields = (array) ($in['alanlar'] ?? []);
        if ($existing) {
            // PATCH: gönderilmeyen alanlar korunur
            $fields += json_decode((string) $existing['veri'], true) ?: [];
        }
        [$id, $errors] = Content::saveEntry($name, $fields, [
            'baslik' => $in['baslik'] ?? null, 'slug' => $in['slug'] ?? '', 'durum' => $in['durum'] ?? ($existing['durum'] ?? 'taslak'),
            'yayin_tarihi' => $in['yayin_tarihi'] ?? '', 'sira' => $in['sira'] ?? null,
        ], $existing, (string) ($in['dil'] ?? App::$lang));
        if ($errors) {
            bz_json(['hata' => 'Doğrulama hatası', 'hatalar' => $errors], 422);
        }
        bz_log('api_yazma', $token['ad'] . " → $name #$id");
        bz_json(['ok' => true, 'veri' => Content::publicView(Content::find($id))], $existing ? 200 : 201);
    }

    /** Anahtar/IP başına dakikada 120 istek (dosya tabanlı, paylaşımlı hosting dostu) */
    private static function rateLimit(?array $token): void
    {
        $dir = BZ_CACHE . '/hiz';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $key = $dir . '/' . md5(($token['id'] ?? 'ip') . bz_ip()) . '-' . date('YmdHi');
        $n = (int) @file_get_contents($key) + 1;
        @file_put_contents($key, (string) $n, LOCK_EX);
        if (random_int(1, 50) === 1) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (filemtime($f) < time() - 120) {
                    @unlink($f);
                }
            }
        }
        if ($n > 120) {
            header('Retry-After: 60');
            bz_json(['hata' => 'Çok fazla istek. Bir dakika sonra tekrar deneyin.'], 429);
        }
    }
}
