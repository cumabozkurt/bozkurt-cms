<?php
declare(strict_types=1);

namespace Bozkurt;

/** sitemap.xml, robots.txt, RSS, llms.txt / llms-full.txt ve sayfaların Markdown sürümleri */
final class Feeds
{
    /** Eğitim için veri toplayan botlar ve yapay zekâ arama/yanıt botları */
    public const AI_TRAINING_BOTS = ['GPTBot', 'ClaudeBot', 'anthropic-ai', 'CCBot', 'Google-Extended', 'Applebot-Extended', 'Bytespider', 'meta-externalagent', 'Amazonbot', 'cohere-ai', 'Diffbot', 'omgili', 'img2dataset'];
    public const AI_SEARCH_BOTS = ['OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'DuckAssistBot', 'MistralAI-User'];

    public static function sitemap(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        $base = App::siteUrl();
        $multi = App::isMultilingual();
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
        foreach (Content::templates() as $name => $t) {
            if (($t['meta']['sitemap'] ?? 'evet') === 'hayir') {
                continue;
            }
            $home = in_array($name, ['ana-sayfa', 'index'], true);
            $langs = App::languages();
            if (!$t['meta']['coklu']) {
                $langs = array_values(array_filter($langs, fn($l) => $l === App::defaultLang() || !Content::translationPending($name, $l)));
            }
            foreach ($langs as $lang) {
                $alts = '';
                if ($multi && count($langs) > 1) {
                    foreach ($langs as $l2) {
                        $alts .= '<xhtml:link rel="alternate" hreflang="' . $l2 . '" href="' . e($base . App::url($home ? '' : $name, $l2)) . '"/>';
                    }
                }
                $x .= '<url><loc>' . e($base . App::url($home ? '' : $name, $lang)) . '</loc>' . $alts . '<priority>' . ($home ? '1.0' : '0.8') . '</priority></url>';
            }
            if ($t['meta']['coklu']) {
                $rows = App::db()->all("SELECT * FROM bz_icerik WHERE sablon = ? AND slug != '' AND durum = 'yayinda' AND yayin_tarihi <= ? ORDER BY yayin_tarihi DESC LIMIT 20000", [$name, bz_now()]);
                $groups = [];
                foreach ($rows as $r) {
                    $groups[(int) $r['grup'] ?: (int) $r['id']][$r['dil']] = $r;
                }
                foreach ($rows as $r) {
                    $seo = json_decode((string) $r['seo'], true) ?: [];
                    if (!empty($seo['noindex']) || !in_array($r['dil'], App::languages(), true)) {
                        continue;
                    }
                    $alts = '';
                    $g = $groups[(int) $r['grup'] ?: (int) $r['id']] ?? [];
                    if ($multi && count($g) > 1) {
                        foreach ($g as $l2 => $r2) {
                            $alts .= '<xhtml:link rel="alternate" hreflang="' . e($l2) . '" href="' . e($base . Content::url($r2)) . '"/>';
                        }
                    }
                    $img = '';
                    foreach (json_decode((string) $r['veri'], true) ?: [] as $v) {
                        if (is_string($v) && preg_match('#^yuklemeler/.+\.(jpe?g|png|webp|gif|avif)$#i', $v)) {
                            $img = '<image:image><image:loc>' . e($base . bz_upload_url($v)) . '</image:loc></image:image>';
                            break;
                        }
                    }
                    $x .= '<url><loc>' . e($base . Content::url($r)) . '</loc>' . $alts . '<lastmod>' . date('c', strtotime($r['guncelleme'])) . '</lastmod>' . $img . '<priority>0.6</priority></url>';
                }
            }
        }
        echo $x . '</urlset>';
        exit;
    }

    public static function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (App::setting('arama_motoru_engelle') === '1') {
            echo "User-agent: *\nDisallow: /\n";
            exit;
        }
        $out = '';
        $policy = App::setting('yz_botlari', 'sadece_arama');
        if ($policy === 'engelle') {
            foreach (array_merge(self::AI_TRAINING_BOTS, self::AI_SEARCH_BOTS) as $b) {
                $out .= "User-agent: $b\n";
            }
            $out .= "Disallow: /\n\n";
        } elseif ($policy === 'sadece_arama') {
            foreach (self::AI_TRAINING_BOTS as $b) {
                $out .= "User-agent: $b\n";
            }
            $out .= "Disallow: /\n\n";
        }
        $out .= "User-agent: *\nDisallow: /yonetim/\nDisallow: /veri/\nDisallow: /bozkurt/\nDisallow: /odeme/\nDisallow: /*?q=\n\n";
        $out .= 'Sitemap: ' . App::siteUrl() . App::url('sitemap.xml', App::defaultLang()) . "\n";
        $extra = trim((string) App::setting('robots_ek', ''));
        echo $out . ($extra !== '' ? "\n" . $extra . "\n" : '');
        exit;
    }

    public static function rss(?string $tpl): never
    {
        $tpls = $tpl ? [$tpl] : array_keys(array_filter(Content::templates(), fn($t) => $t['meta']['coklu']));
        $items = [];
        foreach ($tpls as $name) {
            if (!Content::template($name)) {
                continue;
            }
            $items = array_merge($items, Content::query(['sablon' => $name, 'limit' => 20])['ogeler']);
        }
        usort($items, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));
        $items = array_slice($items, 0, 20);
        header('Content-Type: application/rss+xml; charset=utf-8');
        $site = App::setting('site_adi', 'BOZKURT CMS');
        $x = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
        $x .= '<title>' . e($site) . '</title><link>' . e(App::siteUrl() . '/') . '</link><description>' . e(App::setting('site_aciklama', '')) . '</description><language>' . e(App::$lang) . '</language>';
        $x .= '<atom:link href="' . e(App::siteUrl() . App::url(($tpl ? $tpl . '/' : '') . 'rss.xml')) . '" rel="self" type="application/rss+xml"/>';
        foreach ($items as $it) {
            $x .= '<item><title>' . e($it['baslik']) . '</title><link>' . e($it['tam_url']) . '</link><guid>' . e($it['tam_url']) . '</guid>' .
                '<pubDate>' . date('r', strtotime($it['tarih'])) . '</pubDate><description>' . e($it['ozet']) . '</description></item>';
        }
        echo $x . '</channel></rss>';
        exit;
    }

    /* ------------------------------------------------------------ yapay zekâ dostu çıktılar */

    /** llms.txt standardı (llmstxt.org): sitenin yapay zekâ için özeti ve bağlantı haritası */
    public static function llmsText(bool $full): string
    {
        $site = App::setting('site_adi', 'BOZKURT CMS');
        $md = '# ' . $site . "\n\n> " . (App::setting('site_aciklama', '') ?: $site) . "\n\n";
        if ($intro = trim((string) App::setting('llms_giris', ''))) {
            $md .= $intro . "\n\n";
        }
        $base = App::siteUrl();
        $budget = 400_000; // llms-full.txt boyut sınırı
        foreach (Content::templates() as $name => $t) {
            if (($t['meta']['sitemap'] ?? 'evet') === 'hayir' || ($t['meta']['llms'] ?? 'evet') === 'hayir') {
                continue;
            }
            $md .= '## ' . $t['meta']['baslik'] . "\n\n";
            if (!$t['meta']['coklu']) {
                $row = Content::single($name);
                $url = $base . self::mdUrl($row);
                if ($full) {
                    $md .= self::entryMarkdown($row) . "\n\n";
                } else {
                    $md .= '- [' . ($row['baslik'] ?: $t['meta']['baslik']) . "]($url)\n\n";
                }
                continue;
            }
            foreach (Content::query(['sablon' => $name, 'limit' => $full ? 200 : 50])['ogeler'] as $it) {
                $row = Content::find($it['id']);
                if (!$row || !empty($it['noindex'])) {
                    continue;
                }
                if ($full) {
                    $chunk = self::entryMarkdown($row) . "\n\n---\n\n";
                    if (strlen($md) + strlen($chunk) > $budget) {
                        break 2;
                    }
                    $md .= $chunk;
                } else {
                    $md .= '- [' . $it['baslik'] . '](' . $base . self::mdUrl($row) . ')' . ($it['ozet'] ? ': ' . Str::excerpt($it['ozet'], 120) : '') . "\n";
                }
            }
            $md .= "\n";
        }
        if (!$full) {
            $md .= "## İsteğe bağlı\n\n- [Tüm içerik (tek dosya)]($base/llms-full.txt)\n- [Site haritası]($base/sitemap.xml)\n";
        }
        return $md;
    }

    public static function llms(bool $full): never
    {
        if (App::setting('yz_llms', '1') !== '1') {
            Site::notFound();
            exit;
        }
        header('Content-Type: text/markdown; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo self::llmsText($full);
        exit;
    }

    private static function mdUrl(array $row): string
    {
        $u = Content::url($row);
        return rtrim($u, '/') === rtrim(App::$basePath, '/') ? App::$basePath . 'index.md' : rtrim($u, '/') . '.md';
    }

    /** Bir içeriğin Markdown gösterimi (başlık, meta, tüm metin alanları) */
    public static function entryMarkdown(array $row): string
    {
        $it = Content::hydrate($row);
        $t = Content::template($row['sablon']);
        $md = '# ' . $it['baslik'] . "\n\n";
        $md .= 'Kaynak: ' . $it['tam_url'] . ($t['meta']['coklu'] ? "  \nYayın: " . date('Y-m-d', strtotime($it['tarih'])) : '') . "\n\n";
        foreach ($t['alanlar'] ?? [] as $ad => $def) {
            if (empty($def['api'])) {
                continue;
            }
            $v = $it[$ad] ?? '';
            switch ($def['tur']) {
                case 'zengin':
                case 'markdown':
                    $md .= Markdown::fromHtml((string) $v) . "\n\n";
                    break;
                case 'metin':
                case 'uzunmetin':
                case 'secim':
                case 'fiyat':
                case 'tarih':
                case 'telefon':
                case 'eposta':
                    if ((string) $v !== '' && $ad !== 'baslik') {
                        $label = $def['etiket'];
                        $val = $def['tur'] === 'fiyat' ? Str::tl($v) : (string) $v;
                        $md .= (mb_strlen($val) > 80 ? "**$label:**\n\n$val" : "**$label:** $val") . "\n\n";
                    }
                    break;
                case 'tekrar':
                    foreach ((array) $v as $r) {
                        $line = implode(' — ', array_filter(array_map(fn($x) => is_string($x) && !str_contains($x, '/yuklemeler/') ? strip_tags($x) : '', $r)));
                        if ($line !== '') {
                            $md .= '- ' . $line . "\n";
                        }
                    }
                    $md .= "\n";
                    break;
            }
        }
        return trim($md);
    }

    /** /yol.md isteği: yayımlanmış sayfanın Markdown sürümü */
    public static function markdownFor(string $path): bool
    {
        if (App::setting('yz_markdown', '1') !== '1') {
            return false;
        }
        $path = preg_replace('/\.md$/', '', $path) ?? '';
        $seg = $path === 'index' || $path === '' ? [] : explode('/', $path);
        $tplName = $seg[0] ?? (Content::template('ana-sayfa') ? 'ana-sayfa' : 'index');
        $tpl = Content::template($tplName);
        if (!$tpl || count($seg) > 2) {
            return false;
        }
        if ($tpl['meta']['coklu']) {
            if (!isset($seg[1])) {
                $md = '# ' . $tpl['meta']['baslik'] . "\n\n";
                foreach (Content::query(['sablon' => $tplName, 'limit' => 100])['ogeler'] as $it) {
                    $md .= '- [' . $it['baslik'] . '](' . App::siteUrl() . self::mdUrl(Content::find($it['id'])) . ")\n";
                }
            } else {
                $row = Content::findBySlug($tplName, $seg[1]);
                if (!$row) {
                    return false;
                }
                $md = self::entryMarkdown($row);
            }
        } else {
            $md = self::entryMarkdown(Content::single($tplName));
        }
        header('Content-Type: text/markdown; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $md . "\n";
        return true;
    }
}
