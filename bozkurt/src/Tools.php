<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Araçlar: tek tıkla güncelleme, WordPress içe aktarma, statik site dışa aktarma.
 */
final class Tools
{
    /* ------------------------------------------------------------ güncelleme */

    /** Korunan yollar: güncelleme bunlara asla dokunmaz */
    private const KEEP = ['veri/', 'yuklemeler/', 'sablonlar/', 'tema/', '.htaccess', 'web.config'];

    public static function latestRelease(): ?array
    {
        // Güvenlik: depo yalnızca veri/yapilandirma.php'den değiştirilebilir (ele geçirilmiş panel hesabı kod yükleyemesin)
        $repo = (string) (App::$config['guncelleme_deposu'] ?? BZ_REPO);
        if (!preg_match('#^[A-Za-z0-9_.\-]+/[A-Za-z0-9_.\-]+$#', $repo) || str_starts_with($repo, 'KULLANICI/')) {
            return null;
        }
        [$code, $res] = bz_http('GET', "https://api.github.com/repos/$repo/releases/latest", null, ['Accept: application/vnd.github+json'], 10);
        $j = json_decode($res, true);
        if ($code !== 200 || !isset($j['tag_name'])) {
            return null;
        }
        $zip = $sum = null;
        foreach ($j['assets'] ?? [] as $a) {
            if (str_ends_with($a['name'], '.zip')) {
                $zip = $a['browser_download_url'];
            } elseif (str_ends_with($a['name'], '.sha256')) {
                $sum = $a['browser_download_url'];
            }
        }
        return ['surum' => ltrim($j['tag_name'], 'v'), 'notlar' => $j['body'] ?? '', 'zip' => $zip, 'sha256' => $sum, 'tarih' => $j['published_at'] ?? ''];
    }

    /** @return true|string */
    public static function update(): bool|string
    {
        if (!class_exists('ZipArchive')) {
            return 'Sunucuda ZipArchive yok.';
        }
        $rel = self::latestRelease();
        if (!$rel || !$rel['zip'] || !$rel['sha256']) {
            return 'Sürüm bilgisi veya imza dosyası (.sha256) bulunamadı.';
        }
        if (version_compare($rel['surum'], BZ_VERSION, '<=')) {
            return 'Zaten en güncel sürümü kullanıyorsunuz.';
        }
        foreach ([$rel['zip'], $rel['sha256']] as $u) {
            if (!preg_match('#^https://(github\.com|objects\.githubusercontent\.com)/#', $u)) {
                return 'Güncelleme yalnızca GitHub sürümlerinden indirilebilir.';
            }
        }
        @set_time_limit(300);
        [$c1, $zipData] = self::download($rel['zip']);
        [$c2, $sumData] = self::download($rel['sha256']);
        $expected = strtolower(substr(trim($sumData), 0, 64));
        if ($c1 !== 200 || $c2 !== 200 || !preg_match('/^[a-f0-9]{64}$/', $expected) || !hash_equals($expected, hash('sha256', $zipData))) {
            return 'İndirilen paket doğrulanamadı (SHA-256 uyuşmuyor). Güncelleme iptal edildi.';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bzg');
        file_put_contents($tmp, $zipData);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            return 'Paket açılamadı.';
        }
        // Önce tam yedek
        $backupDir = BZ_DATA . '/yedekler';
        @mkdir($backupDir, 0700, true);
        file_put_contents($backupDir . '/guncelleme-oncesi-' . date('Ymd-His') . '.json', json_encode(Backup::export(), JSON_UNESCAPED_UNICODE));
        $written = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $relPath = preg_replace('#^[^/]+/#', '', $name) ?? ''; // "bozkurt-cms/..." kök klasörünü at
            if ($relPath === '' || str_ends_with($relPath, '/') || str_contains($relPath, '..') || str_starts_with($relPath, '/') || str_contains($relPath, "\0") || str_contains($relPath, '\\')) {
                continue; // zip-slip koruması
            }
            foreach (self::KEEP as $k) {
                if (str_starts_with($relPath, $k)) {
                    continue 2;
                }
            }
            $dest = BZ_ROOT . '/' . $relPath;
            @mkdir(dirname($dest), 0755, true);
            if (file_put_contents($dest, (string) $zip->getFromIndex($i)) !== false) {
                $written++;
            }
        }
        $zip->close();
        @unlink($tmp);
        Cache::clearAll();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        bz_log('guncelleme', BZ_VERSION . ' → ' . $rel['surum'] . " ($written dosya)");
        return true;
    }

    private static function download(string $url): array
    {
        // GitHub varlıkları yönlendirme yapar; yalnızca GitHub alan adlarına izin verilerek izlenir
        for ($i = 0; $i < 4; $i++) {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_TIMEOUT => 120, CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_USERAGENT => 'BOZKURT-CMS', CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
                $body = (string) curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
                curl_close($ch);
            } else {
                [$code, $body] = bz_http('GET', $url, null, [], 120);
                $loc = '';
            }
            if (in_array($code, [301, 302, 303, 307, 308], true) && preg_match('#^https://([a-z0-9.\-]+\.)?(github\.com|githubusercontent\.com)/#', $loc)) {
                $url = $loc;
                continue;
            }
            return [$code, $body];
        }
        return [0, ''];
    }

    /* ------------------------------------------------------------ WordPress içe aktarma */

    /**
     * WordPress › Araçlar › Dışa aktar ile alınan WXR (XML) dosyasını içe aktarır.
     * @return array{eklenen:int, atlanan:int, hatalar:array}
     */
    public static function importWordPress(string $file, string $tplName, array $map, bool $redirects): array
    {
        $out = ['eklenen' => 0, 'atlanan' => 0, 'hatalar' => []];
        $tpl = Content::template($tplName);
        if (!$tpl || !$tpl['meta']['coklu']) {
            $out['hatalar'][] = 'Hedef şablon çoklu (ör. blog) olmalı.';
            return $out;
        }
        if (filesize($file) > 50 * 1048576) {
            $out['hatalar'][] = 'Dosya 50 MB\'tan büyük.';
            return $out;
        }
        libxml_use_internal_errors(true);
        // Dış varlıklar kapalı (XXE koruması), ağ erişimi yok
        $xml = simplexml_load_string((string) file_get_contents($file), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if (!$xml) {
            $out['hatalar'][] = 'XML okunamadı. WordPress dışa aktarım dosyası (WXR) seçin.';
            return $out;
        }
        $ns = $xml->getNamespaces(true);
        $types = array_filter(array_map('trim', explode(',', $map['turler'] ?? 'post')));
        foreach ($xml->channel->item as $item) {
            $wp = $item->children($ns['wp'] ?? 'http://wordpress.org/export/1.2/');
            $type = (string) $wp->post_type;
            $status = (string) $wp->status;
            if (!in_array($type, $types, true) || !in_array($status, ['publish', 'draft', 'future'], true)) {
                $out['atlanan']++;
                continue;
            }
            $content = (string) $item->children($ns['content'] ?? 'http://purl.org/rss/1.0/modules/content/')->encoded;
            $excerpt = (string) $item->children($ns['excerpt'] ?? 'http://wordpress.org/export/1.2/excerpt/')->encoded;
            $cats = [];
            foreach ($item->category as $c) {
                if ((string) $c['domain'] === 'category') {
                    $cats[] = (string) $c;
                }
            }
            $fields = [];
            if (!empty($map['icerik'])) {
                $fields[$map['icerik']] = wpautop($content);
            }
            if (!empty($map['ozet']) && $excerpt !== '') {
                $fields[$map['ozet']] = $excerpt;
            }
            if (!empty($map['kategori']) && $cats) {
                $fields[$map['kategori']] = $cats[0];
            }
            $slug = (string) $wp->post_name ?: Str::slug((string) $item->title);
            if (Content::findBySlug($tplName, Str::slug($slug), false, App::defaultLang())) {
                $out['atlanan']++;
                continue;
            }
            [$id, $err] = Content::saveEntry($tplName, $fields, [
                'baslik' => html_entity_decode((string) $item->title, ENT_QUOTES, 'UTF-8'), 'slug' => $slug,
                'durum' => $status === 'draft' ? 'taslak' : 'yayinda', 'yayin_tarihi' => (string) $wp->post_date,
            ], null, App::defaultLang(), Auth::user()['id'] ?? null);
            if ($err) {
                $out['hatalar'][] = (string) $item->title . ': ' . implode(' ', $err);
                continue;
            }
            $out['eklenen']++;
            if ($redirects && ($link = (string) $item->link)) {
                Redirects::add($link, Content::url(Content::find($id)));
            }
        }
        bz_log('wp_ice_aktar', "{$out['eklenen']} içerik → $tplName");
        return $out;
    }

    /* ------------------------------------------------------------ statik dışa aktarma */

    /** Tüm siteyi statik HTML olarak ZIP'ler (Netlify, Cloudflare Pages, GitHub Pages). */
    public static function exportStatic(): never
    {
        if (!class_exists('ZipArchive')) {
            exit('ZipArchive yok.');
        }
        @set_time_limit(600);
        $paths = [];
        foreach (App::languages() as $lang) {
            App::$lang = $lang;
            foreach (Content::templates() as $name => $t) {
                $home = in_array($name, ['ana-sayfa', 'index'], true);
                $prefix = $lang === App::defaultLang() ? '' : $lang . '/';
                $paths[$prefix . ($home ? '' : $name)] = [$lang, $home ? '' : $name];
                if ($t['meta']['coklu']) {
                    foreach (App::db()->all("SELECT slug FROM bz_icerik WHERE sablon = ? AND dil = ? AND slug != '' AND durum = 'yayinda' AND yayin_tarihi <= ? LIMIT 20000", [$name, $lang, bz_now()]) as $r) {
                        $paths[$prefix . $name . '/' . $r['slug']] = [$lang, $name . '/' . $r['slug']];
                    }
                }
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bzs');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        App::$config['guzel_url'] = true;
        foreach ($paths as $out => [$lang, $p]) {
            App::$lang = $lang;
            [$code, $html] = Site::resolve($p);
            if ($code === 200) {
                $zip->addFromString(($out === '' ? '' : $out . '/') . 'index.html', $html);
            }
        }
        App::$lang = App::defaultLang();
        ob_start();
        Site::notFound();
        $zip->addFromString('404.html', (string) ob_get_clean());
        foreach (['tema', 'yuklemeler'] as $dir) {
            if (!is_dir(BZ_ROOT . '/' . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BZ_ROOT . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $rel = $dir . '/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(BZ_ROOT . '/' . $dir))), '/');
                if (!preg_match('/\.(php|htaccess)$|\/index\.php$|\.gitkeep$/', $rel)) {
                    $zip->addFile($f->getPathname(), $rel);
                }
            }
        }
        $zip->close();
        http_response_code(200);
        bz_log('statik_disa_aktar', count($paths) . ' sayfa');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="statik-site-' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}

/** WordPress'in paragraf biçimlendirmesi (çift satır sonu → <p>) */
function wpautop(string $t): string
{
    if (trim($t) === '') {
        return '';
    }
    if (preg_match('#<(p|div|h[1-6]|ul|ol|table|figure|blockquote)\b#i', $t)) {
        return $t;
    }
    $parts = preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", trim($t))) ?: [];
    return implode("\n", array_map(fn($p) => '<p>' . nl2br(trim($p), false) . '</p>', $parts));
}
