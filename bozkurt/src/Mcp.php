<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * MCP (Model Context Protocol) sunucusu — Streamable HTTP, JSON-RPC 2.0.
 * Claude, ChatGPT, Cursor gibi yapay zekâ istemcileri sitenizin içeriğini okuyabilir ve (yazma anahtarıyla) taslak oluşturabilir.
 *   Adres: https://alanadiniz.com/mcp   ·   Başlık: Authorization: Bearer bz_...
 */
final class Mcp
{
    private const PROTOCOL = '2025-06-18';

    public static function handle(): never
    {
        header('Cache-Control: no-store');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            header('Allow: POST');
            bz_json(['hata' => 'MCP uç noktası yalnızca POST (JSON-RPC 2.0) kabul eder.'], 405);
        }
        if (App::setting('mcp_acik') !== '1') {
            bz_json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'MCP kapalı. Yönetim › Ayarlar › Yapay Zekâ.']], 403);
        }
        $token = Tokens::fromRequest();
        if (!$token) {
            header('WWW-Authenticate: Bearer');
            bz_json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'Geçerli bir API anahtarı gerekli (Bearer bz_...).']], 401);
        }
        $req = json_decode((string) file_get_contents('php://input', false, null, 0, 1_000_000), true);
        if (!is_array($req)) {
            bz_json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Ayrıştırma hatası']], 400);
        }
        if (array_is_list($req)) {
            $out = array_values(array_filter(array_map(fn($r) => is_array($r) ? self::dispatch($r, $token) : null, array_slice($req, 0, 20))));
            $out ? bz_json($out) : self::accepted();
        }
        $res = self::dispatch($req, $token);
        $res === null ? self::accepted() : bz_json($res);
    }

    private static function accepted(): never
    {
        http_response_code(202);
        exit;
    }

    private static function dispatch(array $r, array $token): ?array
    {
        $id = $r['id'] ?? null;
        $m = (string) ($r['method'] ?? '');
        if (!array_key_exists('id', $r)) {
            return null; // bildirim (ör. notifications/initialized)
        }
        try {
            $result = match ($m) {
                'initialize' => [
                    'protocolVersion' => self::PROTOCOL,
                    'capabilities' => ['tools' => ['listChanged' => false], 'resources' => ['listChanged' => false]],
                    'serverInfo' => ['name' => 'bozkurt-cms', 'title' => App::setting('site_adi', 'BOZKURT CMS'), 'version' => BZ_VERSION],
                    'instructions' => 'Bu sunucu "' . App::setting('site_adi', '') . '" sitesinin içeriğine erişim sağlar. Önce sablonlari_listele ile içerik türlerini öğrenin.',
                ],
                'ping' => (object) [],
                'tools/list' => ['tools' => self::tools($token)],
                'tools/call' => self::call((string) ($r['params']['name'] ?? ''), (array) ($r['params']['arguments'] ?? []), $token),
                'resources/list' => ['resources' => [['uri' => App::siteUrl() . '/llms.txt', 'name' => 'llms.txt', 'mimeType' => 'text/markdown']]],
                'resources/read' => ['contents' => [['uri' => App::siteUrl() . '/llms.txt', 'mimeType' => 'text/markdown', 'text' => Feeds::llmsText(false)]]],
                default => throw new \DomainException('Yöntem bulunamadı: ' . $m, -32601),
            };
            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\DomainException $e) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $e->getCode() ?: -32602, 'message' => $e->getMessage()]];
        }
    }

    private static function tools(array $token): array
    {
        $t = [
            ['name' => 'sablonlari_listele', 'description' => 'Sitedeki içerik türlerini (şablonları), alanlarını ve dilleri listeler.', 'inputSchema' => ['type' => 'object', 'properties' => (object) []]],
            ['name' => 'icerik_listele', 'description' => 'Bir şablondaki yayımlanmış içerikleri listeler.', 'inputSchema' => ['type' => 'object', 'properties' => [
                'sablon' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'default' => 20], 'sayfa' => ['type' => 'integer', 'default' => 1],
                'dil' => ['type' => 'string'], 'filtre' => ['type' => 'string', 'description' => 'alan=deger;alan2!=deger']], 'required' => ['sablon']]],
            ['name' => 'icerik_getir', 'description' => 'Tek bir içeriği tüm alanlarıyla getirir (tekil sayfalarda slug boş bırakılır).', 'inputSchema' => ['type' => 'object', 'properties' => [
                'sablon' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'dil' => ['type' => 'string']], 'required' => ['sablon']]],
            ['name' => 'ara', 'description' => 'Tüm sitede tam metin arama yapar.', 'inputSchema' => ['type' => 'object', 'properties' => [
                'sorgu' => ['type' => 'string'], 'dil' => ['type' => 'string']], 'required' => ['sorgu']]],
        ];
        if ($token['kapsam'] === 'yaz') {
            $t[] = ['name' => 'taslak_olustur', 'description' => 'Yeni içeriği TASLAK olarak oluşturur (yayınlama insan onayıyla yapılır).', 'inputSchema' => ['type' => 'object', 'properties' => [
                'sablon' => ['type' => 'string'], 'baslik' => ['type' => 'string'], 'alanlar' => ['type' => 'object'], 'dil' => ['type' => 'string']], 'required' => ['sablon', 'baslik']]];
            $t[] = ['name' => 'icerik_guncelle', 'description' => 'Var olan içeriğin alanlarını günceller; durum değişmez. Önceki hâl sürüm geçmişine kaydedilir.', 'inputSchema' => ['type' => 'object', 'properties' => [
                'sablon' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'baslik' => ['type' => 'string'], 'alanlar' => ['type' => 'object'], 'dil' => ['type' => 'string']], 'required' => ['sablon']]];
        }
        return $t;
    }

    private static function call(string $name, array $a, array $token): array
    {
        if (isset($a['dil']) && in_array($a['dil'], App::languages(), true)) {
            App::$lang = (string) $a['dil'];
        }
        $tplName = (string) ($a['sablon'] ?? '');
        $needTpl = in_array($name, ['icerik_listele', 'icerik_getir', 'taslak_olustur', 'icerik_guncelle'], true);
        $tpl = $needTpl ? Content::template($tplName) : null;
        if ($needTpl && !$tpl) {
            return self::text('Şablon bulunamadı: ' . $tplName, true);
        }
        if (in_array($name, ['taslak_olustur', 'icerik_guncelle'], true) && $token['kapsam'] !== 'yaz') {
            return self::text('Bu araç için yazma yetkisi gerekir.', true);
        }
        switch ($name) {
            case 'sablonlari_listele':
                $out = [];
                foreach (Content::templates() as $n => $t) {
                    $out[] = ['ad' => $n, 'baslik' => $t['meta']['baslik'], 'coklu' => $t['meta']['coklu'],
                        'alanlar' => array_map(fn($d) => $d['tur'] . ' — ' . $d['etiket'], array_filter($t['alanlar'], fn($d) => !empty($d['api'])))];
                }
                return self::json(['sablonlar' => $out, 'diller' => App::languages()]);
            case 'icerik_listele':
                $r = Content::query(['sablon' => $tplName, 'limit' => min(50, max(1, (int) ($a['limit'] ?? 20))), 'sayfa' => (int) ($a['sayfa'] ?? 1), 'filtre' => (string) ($a['filtre'] ?? '')]);
                return self::json(['toplam' => $r['toplam'], 'ogeler' => array_map(fn($i) => ['baslik' => $i['baslik'], 'slug' => $i['slug'], 'tarih' => $i['tarih'], 'url' => $i['tam_url'], 'ozet' => $i['ozet']], $r['ogeler'])]);
            case 'icerik_getir':
                $row = $tpl['meta']['coklu'] ? Content::findBySlug($tplName, (string) ($a['slug'] ?? '')) : Content::single($tplName);
                return $row ? self::json(Content::publicView($row)) : self::text('İçerik bulunamadı.', true);
            case 'ara':
                $r = Content::query(['sablon' => '*', 'arama' => mb_substr((string) ($a['sorgu'] ?? ''), 0, 100), 'limit' => 20]);
                return self::json(array_map(fn($i) => ['baslik' => $i['baslik'], 'sablon' => $i['sablon'], 'slug' => $i['slug'], 'url' => $i['tam_url'], 'ozet' => $i['ozet']], $r['ogeler']));
            case 'taslak_olustur':
                if (!$tpl['meta']['coklu']) {
                    return self::text('Tekil sayfalar için icerik_guncelle kullanın.', true);
                }
                [$id, $err] = Content::saveEntry($tplName, (array) ($a['alanlar'] ?? []), ['baslik' => (string) ($a['baslik'] ?? ''), 'durum' => 'taslak'], null, App::$lang);
                bz_log('mcp_taslak', $token['ad'] . " → $tplName #$id");
                return $err ? self::json(['hatalar' => $err], true) : self::json(['ok' => true, 'id' => $id, 'yonetim' => App::siteUrl() . App::adminUrl('duzenle', ['id' => $id])]);
            case 'icerik_guncelle':
                $row = $tpl['meta']['coklu'] ? Content::findBySlug($tplName, (string) ($a['slug'] ?? ''), false) : Content::single($tplName);
                if (!$row) {
                    return self::text('İçerik bulunamadı.', true);
                }
                $fields = (array) ($a['alanlar'] ?? []) + (json_decode((string) $row['veri'], true) ?: []);
                [$id, $err] = Content::saveEntry($tplName, $fields, ['baslik' => $a['baslik'] ?? $row['baslik'], 'durum' => $row['durum']], $row);
                bz_log('mcp_guncelle', $token['ad'] . " → $tplName #$id");
                return $err ? self::json(['hatalar' => $err], true) : self::json(['ok' => true, 'id' => $id]);
        }
        throw new \DomainException('Bilinmeyen araç: ' . $name, -32602);
    }

    private static function text(string $t, bool $error = false): array
    {
        return ['content' => [['type' => 'text', 'text' => $t]], 'isError' => $error];
    }

    private static function json(mixed $d, bool $error = false): array
    {
        return self::text((string) json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), $error);
    }
}
