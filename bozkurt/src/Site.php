<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Ön yüz yönlendiricisi.
 *   /                    → sablonlar/ana-sayfa.html
 *   /en/...              → aynı rotalar İngilizce (Ayarlar › Diller)
 *   /hakkimizda          → tekil sayfa      /blog → liste      /blog/ilk-yazi → tekil kayıt
 *   /blog/ilk-yazi.md    → Markdown sürüm (yapay zekâ dostu)
 *   /sitemap.xml /robots.txt /rss.xml /llms.txt /llms-full.txt /api/v1/... /mcp /odeme/...
 */
final class Site
{
    /** Önbelleğe alınabilecek sorgu parametreleri (diğerleri önbelleği atlar: disk doldurma saldırısına karşı) */
    private const CACHE_QUERY = ['sayfa', 'kategori', 'yil', 'ay'];

    public static function run(): void
    {
        if (!App::installed()) {
            bz_redirect(App::$basePath . 'yonetim/');
        }
        App::ensureSchema();
        bz_security_headers();
        self::canonicalRedirect();
        $path = self::path();
        $path = self::detectLang($path);

        // sistem rotaları
        if ($path === 'sitemap.xml') {
            Feeds::sitemap();
        }
        if ($path === 'robots.txt') {
            Feeds::robots();
        }
        if ($path === 'llms.txt' || $path === 'llms-full.txt') {
            Feeds::llms($path === 'llms-full.txt');
        }
        if ($path === 'rss.xml' || preg_match('#^([a-z0-9_\-]+)/rss\.xml$#', $path, $m)) {
            Feeds::rss($m[1] ?? null);
        }
        if (str_starts_with($path, 'api/')) {
            Api::handle(substr($path, 4));
        }
        if ($path === 'mcp') {
            Mcp::handle();
        }
        if (str_starts_with($path, 'odeme/')) {
            Payment::route(substr($path, 6));
            return;
        }
        if ($path === 'yonetim') {
            bz_redirect(App::$basePath . 'yonetim/', 301);
        }

        $loggedIn = Auth::check();
        if (App::setting('bakim_modu') === '1' && !$loggedIn) {
            http_response_code(503);
            header('Retry-After: 3600');
            self::renderSystem('_bakim', 'Sitemiz kısa bir bakımda. Lütfen biraz sonra tekrar ziyaret edin.');
            return;
        }
        if (str_ends_with($path, '.md')) {
            if (Feeds::markdownFor($path)) {
                return;
            }
            self::notFound();
            return;
        }

        $cacheable = !$loggedIn && Cache::enabled() && !array_diff(array_keys($_GET), [...self::CACHE_QUERY, 'yol']);
        if ($cacheable && ($html = Cache::get()) !== null) {
            header('X-Bozkurt-Onbellek: HIT');
            echo $html;
            return;
        }
        Runtime::$inline = $loggedIn && isset($_GET['duzenle']);

        [$code, $html, $tplName, $page, $view] = self::resolve($path, isset($_GET['onizleme']) && $loggedIn);
        if ($code === 404) {
            Redirects::handle($path);
            Redirects::log404($path);
            self::notFound();
            return;
        }
        if ($loggedIn) {
            $html = self::adminBar($html, $tplName, $page, $view);
        } elseif ($cacheable && http_response_code() === 200) {
            Cache::put($html);
        }
        echo $html;
    }

    /**
     * Yinelenen içerik önleme (SEO): /Blog/ → /blog, /index.php → /
     * Yalnızca GET/HEAD ve güzel URL modunda.
     */
    private static function canonicalRedirect(): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true) || isset($_GET['yol']) || empty(App::$config['guzel_url'])) {
            return;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $qs = (string) parse_url($uri, PHP_URL_QUERY);
        $base = App::$basePath;
        $new = $path;
        if ($new === $base . 'index.php') {
            $new = $base;
        }
        if (strlen($new) > strlen($base) && str_ends_with($new, '/')) {
            $new = rtrim($new, '/');
        }
        $decoded = rawurldecode($new);
        if (preg_match('/[A-Z]/', $decoded) && !str_starts_with($decoded, $base . 'yuklemeler/')) {
            $new = strtolower($decoded);
        }
        if ($new !== $path && $new !== '') {
            bz_redirect($new . ($qs !== '' ? '?' . $qs : ''), 301);
        }
    }

    /** @return array{0:int,1:string,2:string,3:?array,4:string} */
    public static function resolve(string $path, bool $preview = false): array
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $tplName = $segments[0] ?? '';
        if ($tplName === '') {
            $tplName = Content::template('ana-sayfa') ? 'ana-sayfa' : (Content::template('index') ? 'index' : '');
        }
        $tpl = $tplName !== '' ? Content::template($tplName) : null;
        if (!$tpl || count($segments) > 2) {
            return [404, '', $tplName, null, ''];
        }
        $view = 'sayfa';
        if ($tpl['meta']['coklu']) {
            if (isset($segments[1])) {
                $row = Content::findBySlug($tplName, $segments[1], !$preview);
                if (!$row) {
                    return [404, '', $tplName, null, ''];
                }
                $page = Content::hydrate($row);
                $view = 'tekil';
            } else {
                $view = 'liste';
                $page = ['baslik' => $tpl['meta']['baslik'], 'url' => App::url($tplName)];
            }
        } else {
            if (isset($segments[1])) {
                return [404, '', $tplName, null, ''];
            }
            $page = Content::hydrate(Content::single($tplName));
        }
        return [200, self::render($tplName, $tpl, $page, $view), $tplName, $page, $view];
    }

    public static function path(): string
    {
        if (isset($_GET['yol'])) {
            $p = (string) $_GET['yol'];
        } else {
            $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
            $p = str_starts_with($uri, App::$basePath) ? substr($uri, strlen(App::$basePath)) : $uri;
            if ($p === 'index.php') {
                $p = '';
            }
        }
        $p = trim($p, '/');
        return preg_match('#^[a-z0-9_\-/.]*$#i', $p) && !str_contains($p, '..') ? strtolower($p) : '__gecersiz__';
    }

    /** "/en/blog" → dil=en, yol=blog */
    private static function detectLang(string $path): string
    {
        App::$lang = App::defaultLang();
        $first = explode('/', $path, 2)[0];
        $mdLang = str_ends_with($first, '.md') && !str_contains($path, '/') ? substr($first, 0, -3) : '';
        if ($mdLang !== '' && $mdLang !== App::defaultLang() && in_array($mdLang, App::languages(), true)) {
            App::$lang = $mdLang;
            return 'index.md'; // /en.md → İngilizce ana sayfanın Markdown sürümü
        }
        if ($first !== App::defaultLang() && in_array($first, App::languages(), true)) {
            App::$lang = $first;
            return (string) (explode('/', $path, 2)[1] ?? '');
        }
        return $path;
    }

    public static function siteVars(): array
    {
        $lang = App::$lang;
        $tr = fn(string $k, string $d = '') => (string) App::setting($lang === App::defaultLang() ? $k : $lang . ':' . $k, App::setting($k, $d));
        return [
            'ad' => $tr('site_adi', 'BOZKURT CMS'),
            'slogan' => $tr('site_slogan'),
            'aciklama' => $tr('site_aciklama'),
            'url' => App::siteUrl(),
            'kok' => App::$basePath,
            'ana' => App::url(''),
            'onek' => rtrim(App::url(''), '/') . '/', // dile göre bağlantı öneki: "/" veya "/en/"
            'dil' => $lang,
            'diller' => App::languages(),
            'yon' => in_array($lang, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr',
            'logo' => bz_upload_url(App::setting('logo', '')),
        ];
    }

    public static function render(string $name, ?array $tpl, ?array $page, string $view): string
    {
        $R = new Runtime([
            'sayfa' => $page,
            'gorunum' => $view,
            'sablon' => ['ad' => $name, 'meta' => $tpl['meta'] ?? []],
            'site' => self::siteVars(),
            'istek' => ['q' => mb_substr((string) ($_GET['q'] ?? ''), 0, 100), 'sayfa' => max(1, (int) ($_GET['sayfa'] ?? 1))],
            'kullanici' => Auth::user() ? ['ad' => Auth::user()['ad']] : null,
            'form' => ['gonderildi' => false, 'basarili' => false, 'hatalar' => [], 'eski' => []],
        ]);
        $compiled = Template::compiled($name);
        ob_start();
        try {
            (static function () use ($R, $compiled) {
                include $compiled;
            })();
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('BOZKURT şablon hatası (' . $name . '): ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            return !empty(App::$config['hata_ayiklama'])
                ? '<pre>' . e($e) . '</pre>'
                : '<h1>Bir hata oluştu</h1><p>Sayfa şu anda görüntülenemiyor.</p>';
        }
        $html = (string) ob_get_clean();
        // <html lang> otomatik: şablon "tr" yazsa bile geçerli dil uygulanır
        if (App::isMultilingual()) {
            $html = preg_replace('/<html([^>]*)\slang="[a-z\-]*"/i', '<html$1 lang="' . App::$lang . '"', $html, 1) ?? $html;
        }
        return $html;
    }

    public static function notFound(): void
    {
        http_response_code(404);
        header('Cache-Control: no-store');
        if (is_file(BZ_TEMPLATES . '/404.html')) {
            echo self::render('404', null, ['baslik' => 'Sayfa bulunamadı', 'noindex' => true], 'sayfa');
            return;
        }
        echo '<!doctype html><meta charset="utf-8"><title>404</title><h1>Sayfa bulunamadı</h1>';
    }

    private static function renderSystem(string $tpl, string $msg): void
    {
        if (is_file(BZ_TEMPLATES . "/$tpl.html")) {
            echo self::render($tpl, null, ['baslik' => App::setting('site_adi', '')], 'sayfa');
            return;
        }
        echo '<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta name="robots" content="noindex"><title>' . e(App::setting('site_adi', '')) .
            '</title><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;background:#0f172a;color:#e2e8f0"><div style="text-align:center"><h1>' .
            e(App::setting('site_adi', '')) . '</h1><p>' . e($msg) . '</p></div></body></html>';
    }

    /** Giriş yapmış kullanıcıya ön yüzde düzenleme çubuğu ve satır içi düzenleme */
    private static function adminBar(string $html, string $tpl, ?array $page, string $view): string
    {
        $edit = match ($view) {
            'tekil' => App::adminUrl('duzenle', ['id' => $page['id']]),
            'liste' => App::adminUrl('icerik', ['sablon' => $tpl, 'dil' => App::$lang]),
            default => App::adminUrl('duzenle', ['sablon' => $tpl, 'dil' => App::$lang]),
        };
        $self = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $inline = Runtime::$inline;
        $btn = 'background:#111827;color:#fff;padding:10px 14px;border-radius:999px;text-decoration:none;box-shadow:0 8px 24px rgba(0,0,0,.25);border:0;font:inherit;cursor:pointer';
        $bar = '<div id="bz-cubuk" style="position:fixed;bottom:16px;right:16px;z-index:99998;display:flex;gap:6px;font:600 13px system-ui,sans-serif">' .
            '<a href="' . e(App::adminUrl()) . '" style="' . $btn . '">🐺 Panel</a>' .
            ($view !== 'liste' ? '<a href="' . e($self . ($inline ? '' : '?duzenle=1')) . '" style="' . $btn . '">' . ($inline ? '✓ Düzenlemeyi bitir' : '✍ Sayfada düzenle') . '</a>' : '') .
            '<a href="' . e($edit) . '" style="' . str_replace('#111827', '#dc2626', $btn) . '">✎ ' . ($view === 'liste' ? 'İçerikleri yönet' : 'Panelde düzenle') . '</a></div>';
        if ($inline) {
            $bar .= '<style>bz-duzenle{outline:2px dashed rgba(220,38,38,.55);outline-offset:3px;border-radius:4px;cursor:text;display:inline}bz-duzenle[data-tur=zengin]{display:block}bz-duzenle:focus{outline:2px solid #dc2626;background:rgba(254,226,226,.35)}bz-duzenle.kaydedildi{outline-color:#16a34a}</style>' .
                '<script>(function(){var t=' . json_encode(bz_csrf_token()) . ',u=' . json_encode(App::adminUrl('satir_ici')) . ';document.querySelectorAll("bz-duzenle").forEach(function(el){el.contentEditable=el.dataset.tur==="zengin"?"true":"plaintext-only";var ilk=el.innerHTML;el.addEventListener("keydown",function(e){if(e.key==="Enter"&&el.dataset.tur==="metin"){e.preventDefault();el.blur()}});el.addEventListener("blur",function(){if(el.innerHTML===ilk)return;var f=new FormData();f.append("_csrf",t);f.append("id",el.dataset.id);f.append("alan",el.dataset.alan);f.append("deger",el.dataset.tur==="zengin"?el.innerHTML:el.innerText);fetch(u,{method:"POST",body:f,credentials:"same-origin",headers:{Accept:"application/json"}}).then(function(r){return r.json()}).then(function(j){if(j.ok){ilk=el.innerHTML;el.classList.add("kaydedildi");setTimeout(function(){el.classList.remove("kaydedildi")},1200)}else alert(j.hata||"Kaydedilemedi")})})})})();</script>';
        }
        return str_contains($html, '</body>') ? str_replace('</body>', $bar . '</body>', $html) : $html . $bar;
    }
}
