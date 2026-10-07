<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Derlenmiş şablonların çalışma zamanı ($R).
 */
final class Runtime
{
    private array $scopes = [];
    private array $rowStack = [];
    private ?array $lastList = null;
    private array $forms = [];

    public function __construct(private array $vars)
    {
    }

    /* ------------------------------------------------------------ kapsamlar */

    public function push(array $s): void
    {
        $this->scopes[] = $s;
    }

    public function pop(): void
    {
        array_pop($this->scopes);
    }

    public function pushRow(array $row): void
    {
        $this->rowStack[] = $row;
        $this->scopes[] = ['satir' => $row];
    }

    public function popRow(): void
    {
        array_pop($this->rowStack);
        array_pop($this->scopes);
    }

    public function set(string $k, string $v): void
    {
        if ($k !== '') {
            $this->vars[$k] = $v;
        }
    }

    public function resolve(string $path): mixed
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if ($path === 'simdi') {
            return bz_now();
        }
        if ($path === 'yil') {
            return date('Y');
        }
        $parts = explode('.', $path);
        $first = array_shift($parts);
        $found = false;
        $v = null;
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (array_key_exists($first, $this->scopes[$i])) {
                $v = $this->scopes[$i][$first];
                $found = true;
                break;
            }
        }
        if (!$found) {
            if (array_key_exists($first, $this->vars)) {
                $v = $this->vars[$first];
            } elseif (is_array($this->vars['sayfa'] ?? null) && array_key_exists($first, $this->vars['sayfa'])) {
                $v = $this->vars['sayfa'][$first]; // {{ baslik }} = {{ sayfa.baslik }}
            } elseif ($first === 'genel' && $parts) {
                return Content::globalValue(array_shift($parts));
            }
        }
        foreach ($parts as $p) {
            if (is_array($v) && array_key_exists($p, $v)) {
                $v = $v[$p];
            } else {
                return null;
            }
        }
        return $v;
    }

    /* ------------------------------------------------------------ alanlar */

    public function field(string $name): string
    {
        if ($this->rowStack) {
            $row = end($this->rowStack);
            $def = $this->subDef($name);
            return $this->render($def, $row[$name] ?? '');
        }
        $tpl = Content::template($this->vars['sablon']['ad'] ?? '');
        $def = $tpl['alanlar'][$name] ?? ['tur' => 'metin'];
        $page = $this->vars['sayfa'] ?? null;
        $html = $this->render($def, is_array($page) ? ($page[$name] ?? '') : '');
        if (self::$inline && !empty($page['id']) && in_array($def['tur'], ['metin', 'zengin', 'uzunmetin'], true)) {
            return '<bz-duzenle data-id="' . (int) $page['id'] . '" data-alan="' . e($name) . '" data-tur="' . e($def['tur']) . '">' . $html . '</bz-duzenle>';
        }
        return $html;
    }

    private function subDef(string $name): array
    {
        $tpl = Content::template($this->vars['sablon']['ad'] ?? '');
        $row = end($this->rowStack) ?: [];
        foreach ($tpl['alanlar'] ?? [] as $def) {
            if ($def['tur'] === 'tekrar' && isset($def['alt'][$name])) {
                return $def['alt'][$name];
            }
            if ($def['tur'] === 'bloklar' && isset($row['_tip'], $def['bloklar'][$row['_tip']]['alt'][$name])) {
                return $def['bloklar'][$row['_tip']]['alt'][$name];
            }
        }
        return ['tur' => 'metin'];
    }

    public function blockIs(string $tip): bool
    {
        $row = end($this->rowStack);
        return is_array($row) && ($row['_tip'] ?? '') === $tip;
    }

    /** Ön yüzde satır içi düzenleme modu (yalnızca giriş yapmış ve ?duzenle=1) */
    public static bool $inline = false;

    /** Şablon metinleri için çeviri sözlüğü: sablonlar/diller/{dil}.json, yoksa yerleşik İngilizce */
    private static array $dict = [];

    public function tr(string $s): string
    {
        $lang = App::$lang;
        if (!isset(self::$dict[$lang])) {
            $file = BZ_TEMPLATES . '/diller/' . $lang . '.json';
            $d = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
            if ($lang === 'en') {
                $d += [
                    'Aydınlatma Metni' => 'Privacy Notice', 'Yalnızca zorunlu' => 'Essential only', 'Tümünü kabul et' => 'Accept all',
                    'Önceki' => 'Previous', 'Sonraki' => 'Next', 'Ana Sayfa' => 'Home', 'Sitede ara…' => 'Search the site…', 'Ara' => 'Search',
                    'İçindekiler' => 'Contents', 'Teşekkürler! Mesajınız bize ulaştı.' => 'Thank you! Your message has been received.',
                    'Kişisel verilerimin {link}Aydınlatma Metni{/link} kapsamında işlenmesini kabul ediyorum.' => 'I agree to the processing of my personal data as described in the {link}Privacy Notice{/link}.',
                    'Sitemizde deneyiminizi iyileştirmek için çerezler kullanıyoruz. Zorunlu çerezler her zaman etkindir; analitik çerezler yalnızca onayınızla çalışır.' => 'We use cookies to improve your experience. Essential cookies are always on; analytics cookies run only with your consent.',
                    'Ticari elektronik ileti almak istiyorum.' => 'I would like to receive commercial electronic messages.', 'Satın al' => 'Buy now',
                    'Ad Soyad' => 'Full name', 'E-posta' => 'E-mail', 'Telefon' => 'Phone', 'Adres' => 'Address', 'Ödemeye geç' => 'Proceed to payment',
                ];
            }
            self::$dict[$lang] = $d;
        }
        return self::$dict[$lang][$s] ?? $s;
    }

    public function global(string $name): string
    {
        $defs = Content::globalDefs();
        $def = $defs[$name] ?? ['tur' => 'metin', 'varsayilan' => ''];
        $v = Content::globalValue($name);
        return $this->render($def, Content::presentValue($def, $v ?? $def['varsayilan']));
    }

    public function rows(string $name): array
    {
        if ($this->rowStack) {
            return [];
        }
        $v = $this->resolve($name);
        return is_array($v) && array_is_list($v) ? $v : [];
    }

    private function render(array $def, mixed $v): string
    {
        return match ($def['tur'] ?? 'metin') {
            'zengin' => self::headingIds((string) $v),
            'uzunmetin' => nl2br(e($v)),
            'onay' => $v ? '1' : '',
            'coklusecim' => e(implode(', ', (array) $v)),
            'fiyat' => $v === '' ? '' : e(Str::tl($v)),
            'tarih' => e(Str::date((string) $v, 'd F Y')),
            'tarihsaat' => e(Str::date((string) $v, 'd F Y H:i')),
            'iliski' => is_array($v) ? e($v['baslik']) : '',
            'video' => self::videoEmbed((string) $v),
            'harita' => self::mapEmbed((string) $v),
            'tekrar', 'bloklar' => '',
            'markdown' => (string) $v,
            'iban' => e(trim(chunk_split((string) $v, 4, ' '))),
            default => e(is_array($v) ? ($v['baslik'] ?? '') : $v),
        };
    }

    public static function headingIds(string $html): string
    {
        return preg_replace_callback('/<(h[23])>(.*?)<\/\1>/is', function ($m) {
            return '<' . $m[1] . ' id="' . Str::slug(strip_tags($m[2]), 60) . '">' . $m[2] . '</' . $m[1] . '>';
        }, $html) ?? $html;
    }

    public static function videoEmbed(string $url): string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            $src = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        } elseif (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            $src = 'https://player.vimeo.com/video/' . $m[1];
        } else {
            return '';
        }
        return '<div class="bz-video" style="position:relative;padding-top:56.25%"><iframe src="' . e($src) .
            '" style="position:absolute;inset:0;width:100%;height:100%;border:0" loading="lazy" allowfullscreen title="Video"></iframe></div>';
    }

    public static function mapEmbed(string $addr): string
    {
        if ($addr === '') {
            return '';
        }
        return '<iframe class="bz-harita" src="https://www.google.com/maps?q=' . rawurlencode($addr) .
            '&output=embed" style="width:100%;height:360px;border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Harita"></iframe>';
    }

    /* ------------------------------------------------------------ çıktı + süzgeçler */

    public function out(string $path, array $filters, bool $escape): string
    {
        $v = $this->resolve($path);
        if ($v === null && preg_match('/^"(.*)"$/s', $path, $m)) {
            $v = $m[1];
        }
        $safe = false;
        foreach ($filters as [$name, $args]) {
            if ($name === 'satirlar') {
                $v = nl2br(e($v));
                $safe = true;
                continue;
            }
            $v = $this->filter($name, $v, $args);
        }
        if (is_bool($v)) {
            $v = $v ? '1' : '';
        }
        if (is_array($v)) {
            $v = $v['baslik'] ?? (array_is_list($v) && !array_filter($v, 'is_array') ? implode(', ', $v) : '');
        }
        return ($escape && !$safe) ? e($v) : (string) $v;
    }

    private function filter(string $f, mixed $v, array $a): mixed
    {
        $s = is_scalar($v) ? (string) $v : '';
        return match ($f) {
            'tarih' => Str::date($s, $a[0] ?? 'd F Y'),
            'once' => Str::date($s, 'once'),
            'kisalt', 'ozet' => Str::excerpt($s, (int) ($a[0] ?? 160)),
            'buyuk' => Str::upper($s),
            'kucuk' => Str::lower($s),
            'baslik' => Str::title($s),
            'varsayilan' => ($v === null || $v === '' || $v === []) ? ($a[0] ?? '') : $v,
            'tl' => $s === '' ? '' : Str::tl($s),
            'sayi' => Str::number($s, (int) ($a[0] ?? 0)),
            'resim' => Media::thumb($s, $a[0] ?? '800x0', $a[1] ?? 'kirp'),
            'json' => json_encode($v, JSON_UNESCAPED_UNICODE),
            'url_kodla' => rawurlencode($s),
            'slug' => Str::slug($s),
            'telefon' => Str::phone($s),
            'tel_link' => 'tel:+90' . substr(preg_replace('/\D/', '', $s) ?? '', -10),
            'whatsapp' => 'https://wa.me/90' . substr(preg_replace('/\D/', '', $s) ?? '', -10),
            'say' => is_array($v) ? count($v) : mb_strlen($s),
            'ilk' => is_array($v) ? (reset($v) ?: '') : mb_substr($s, 0, 1),
            'birlestir' => is_array($v) ? implode($a[0] ?? ', ', array_map(fn($x) => is_array($x) ? ($x['baslik'] ?? '') : $x, $v)) : $s,
            'html_temizle' => strip_tags($s),
            'okuma_suresi' => Str::readingTime($s) . ' dk',
            'video' => self::videoEmbed($s),
            'cevir' => $this->tr($s),
            'resimler' => Media::srcset($s, $a[0] ?? '480,800,1200'),
            'tatil' => (string) Validate::holiday($s),
            'markdown' => Markdown::toHtml($s),
            'md5' => md5($s),
            default => $v,
        };
    }

    /* ------------------------------------------------------------ koşullar */

    public function kosul(string $expr): bool
    {
        foreach (preg_split('/\s+(?:\|\||veya)\s+/u', $expr) ?: [] as $or) {
            $all = true;
            foreach (preg_split('/\s+(?:&&|ve)\s+/u', $or) ?: [] as $and) {
                if (!$this->atom(trim($and))) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }
        return false;
    }

    private function atom(string $e): bool
    {
        if (!preg_match('/^(!|degil\s+)?\s*([a-z0-9_.]+)\s*(?:(==|!=|>=|<=|>|<|icerir)\s*(.+))?$/iu', $e, $m)) {
            return false;
        }
        $neg = !empty($m[1]);
        $left = $this->resolve($m[2]);
        if (empty($m[3])) {
            $r = !($left === null || $left === '' || $left === false || $left === [] || $left === '0');
            return $neg ? !$r : $r;
        }
        $rv = trim($m[4]);
        if (preg_match('/^(["\'])(.*)\1$/s', $rv, $q)) {
            $right = $q[2];
        } elseif (is_numeric($rv)) {
            $right = $rv + 0;
        } else {
            $right = $this->resolve($rv);
        }
        if (is_array($left) && isset($left['slug'])) {
            $left = $left['slug'];
        }
        $ls = is_scalar($left) ? (string) $left : '';
        $r = match ($m[3]) {
            '==' => $ls === (string) $right,
            '!=' => $ls !== (string) $right,
            '>' => (float) $ls > (float) $right,
            '<' => (float) $ls < (float) $right,
            '>=' => (float) $ls >= (float) $right,
            '<=' => (float) $ls <= (float) $right,
            'icerir' => is_array($left) ? in_array((string) $right, $left, true) : str_contains(Str::lower($ls), Str::lower((string) $right)),
            default => false,
        };
        return $neg ? !$r : $r;
    }

    /* ------------------------------------------------------------ liste */

    public function liste(array $a): array
    {
        $o = [
            'sablon' => $a['sablon'] ?? ($this->vars['sablon']['ad'] ?? ''),
            'limit' => $a['limit'] ?? 10,
            'sirala' => $a['sirala'] ?? 'tarih',
            'yon' => $a['yon'] ?? 'azalan',
            'filtre' => $a['filtre'] ?? '',
        ];
        if (isset($a['dil']) && in_array($a['dil'], App::languages(), true)) {
            $o['dil'] = $a['dil'];
        }
        if (($a['arsiv'] ?? '') === 'evet') {
            $o['yil'] = preg_match('/^\d{4}$/', (string) ($_GET['yil'] ?? '')) ? $_GET['yil'] : '';
            $o['ay'] = preg_match('/^\d{1,2}$/', (string) ($_GET['ay'] ?? '')) ? $_GET['ay'] : '';
        }
        if (($a['sayfalama'] ?? '') === 'evet') {
            $o['sayfa'] = (int) ($_GET['sayfa'] ?? 1);
        }
        if (($a['arama'] ?? '') === 'evet') {
            $o['arama'] = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
            if ($o['arama'] === '') {
                return $this->lastList = ['ogeler' => [], 'toplam' => 0, 'sayfa' => 1, 'sayfa_sayisi' => 1, 'limit' => 10];
            }
        }
        foreach (array_filter(array_map('trim', explode(',', $a['url_filtre'] ?? ''))) as $uf) {
            $val = $_GET[$uf] ?? '';
            if (is_string($val) && $val !== '' && preg_match('/^[a-z0-9_]+$/', $uf)) {
                $o['filtre'] = trim($o['filtre'] . ';' . $uf . '=' . str_replace(';', '', mb_substr($val, 0, 100)), ';');
            }
        }
        if (($a['haric_bu'] ?? '') === 'evet' && !empty($this->vars['sayfa']['id'])) {
            $o['haric'] = $this->vars['sayfa']['id'];
        }
        if (isset($a['ilgili']) && !empty($this->vars['sayfa'][$a['ilgili']])) {
            $rel = $this->vars['sayfa'][$a['ilgili']];
            $o['filtre'] = trim($o['filtre'] . ';' . $a['ilgili'] . '=' . (is_array($rel) ? $rel['slug'] : $rel), ';');
            $o['haric'] = $this->vars['sayfa']['id'];
        }
        if (($a['sablon'] ?? '') === '*') {
            $o['sablon'] = '*';
        }
        if ($o['sablon'] === '' || ($o['sablon'] !== '*' && !Content::template($o['sablon']))) {
            return ['ogeler' => [], 'toplam' => 0, 'sayfa' => 1, 'sayfa_sayisi' => 1, 'limit' => 10];
        }
        $res = Content::query($o);
        $this->vars['liste'] = ['toplam' => $res['toplam'], 'sayfa' => $res['sayfa'], 'sayfa_sayisi' => $res['sayfa_sayisi']];
        return $this->lastList = $res;
    }

    /* ------------------------------------------------------------ yerleşik etiketler */

    public function tag_menu(array $a): string
    {
        $current = $this->vars['sablon']['ad'] ?? '';
        $html = '<ul class="' . e($a['sinif'] ?? 'bz-menu') . '">';
        foreach (Content::templates() as $name => $t) {
            if (!$t['meta']['menude']) {
                continue;
            }
            $url = App::url(in_array($name, ['ana-sayfa', 'index'], true) ? '' : $name);
            $active = $name === $current ? ' class="aktif" aria-current="page"' : '';
            $html .= '<li><a href="' . e($url) . '"' . $active . '>' . e($this->tr($t['meta']['menu_adi'] ?? $t['meta']['baslik'])) . '</a></li>';
        }
        return $html . '</ul>';
    }

    public function tag_seo(array $a): string
    {
        $site = $this->vars['site'];
        $p = $this->vars['sayfa'] ?? [];
        $tplTitle = $this->tr($this->vars['sablon']['meta']['baslik'] ?? '');
        $isHome = in_array($this->vars['sablon']['ad'] ?? '', ['ana-sayfa', 'index'], true);
        $gor = $this->vars['gorunum'] ?? 'sayfa';

        $title = $p['seo_baslik'] ?? '';
        $listeEk = [];
        $arsiv = false;
        if ($gor === 'liste') {
            // Süzülmüş ve sayfalanmış listeler ayrı başlık alır (yinelenen başlık önlenir)
            foreach (['kategori', 'etiket'] as $f) {
                if (isset($_GET[$f]) && is_string($_GET[$f]) && $_GET[$f] !== '') {
                    $listeEk[] = mb_substr($_GET[$f], 0, 60);
                }
            }
            if (preg_match('/^\d{4}$/', (string) ($_GET['yil'] ?? ''))) {
                $ay = (int) ($_GET['ay'] ?? 0);
                $listeEk[] = ($ay >= 1 && $ay <= 12 ? Str::AYLAR[$ay] . ' ' : '') . $_GET['yil'];
                $arsiv = true;
            }
            if ((int) ($_GET['sayfa'] ?? 1) > 1) {
                $listeEk[] = $this->tr('Sayfa') . ' ' . (int) $_GET['sayfa'];
            }
        }
        if ($title === '') {
            $base = $gor === 'liste' ? $tplTitle : ($p['baslik'] ?? $tplTitle);
            $title = $isHome ? $site['ad'] . ($site['slogan'] ? ' — ' . $site['slogan'] : '') : $base . ($listeEk ? ' – ' . implode(' – ', $listeEk) : '') . ' | ' . $site['ad'];
        }
        $desc = $p['seo_aciklama'] ?? '';
        if ($desc === '') {
            $desc = ($gor === 'tekil' && !empty($p['ozet'])) ? $p['ozet'] : $site['aciklama'];
            if (mb_strlen($desc) < 70 && $desc !== $site['aciklama'] && $site['aciklama'] !== '') {
                $desc = rtrim($desc, '. ') . '. ' . $site['aciklama'];
            }
        }
        $img = $p['seo_resim'] ?? '';
        if ($img === '') {
            foreach ($p as $v) {
                if (is_string($v) && preg_match('#/yuklemeler/.+\.(jpe?g|png|webp)$#i', $v)) {
                    $img = $v;
                    break;
                }
            }
        }
        $img = $img ?: bz_upload_url(App::setting('varsayilan_resim', ''));
        $abs = fn($u) => $u && !preg_match('#^https?://#', $u) ? App::siteUrl() . $u : $u;
        $canonical = App::siteUrl() . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $cq = [];
        if ($gor === 'liste' && isset($_GET['kategori']) && is_string($_GET['kategori']) && $_GET['kategori'] !== '') {
            $cq['kategori'] = mb_substr($_GET['kategori'], 0, 60);
        }
        if (!empty($_GET['sayfa']) && (int) $_GET['sayfa'] > 1) {
            $cq['sayfa'] = (int) $_GET['sayfa'];
        }
        $canonical .= $cq ? '?' . http_build_query($cq) : '';

        $h = [];
        $h[] = '<title>' . e($title) . '</title>';
        $h[] = '<meta name="description" content="' . e(Str::excerpt($desc, 160)) . '">';
        $h[] = '<link rel="canonical" href="' . e($canonical) . '">';
        $tplNoindex = in_array(strtolower((string) ($this->vars['sablon']['meta']['noindex'] ?? '')), ['evet', '1', 'true'], true);
        if (!empty($p['noindex']) || $tplNoindex || App::setting('arama_motoru_engelle') === '1') {
            $h[] = '<meta name="robots" content="noindex, nofollow">';
        } elseif ($arsiv) {
            $h[] = '<meta name="robots" content="noindex, follow">'; // tarih arşivleri ince içeriktir
        }
        $h[] = '<meta property="og:type" content="' . ($gor === 'tekil' ? 'article' : 'website') . '">';
        $h[] = '<meta property="og:title" content="' . e($title) . '">';
        $h[] = '<meta property="og:description" content="' . e(Str::excerpt($desc, 200)) . '">';
        $h[] = '<meta property="og:url" content="' . e($canonical) . '">';
        $h[] = '<meta property="og:site_name" content="' . e($site['ad']) . '">';
        $h[] = '<meta property="og:locale" content="' . e(App::$lang === 'tr' ? 'tr_TR' : App::$lang) . '">';
        if ($img) {
            $h[] = '<meta property="og:image" content="' . e($abs($img)) . '">';
            $h[] = '<meta name="twitter:card" content="summary_large_image">';
        }
        if ($v = App::setting('google_dogrulama')) {
            $h[] = '<meta name="google-site-verification" content="' . e($v) . '">';
        }
        if ($v = App::setting('yandex_dogrulama')) {
            $h[] = '<meta name="yandex-verification" content="' . e($v) . '">';
        }
        if ($v = App::setting('favicon')) {
            $h[] = '<link rel="icon" href="' . e(bz_upload_url($v)) . '">';
        }
        $h[] = '<link rel="alternate" type="application/rss+xml" title="' . e($site['ad']) . '" href="' . e(App::url('rss.xml')) . '">';
        $h[] = '<meta name="robots" content="max-image-preview:large">';
        if ($gor === 'tekil') {
            $h[] = '<meta property="article:published_time" content="' . e(date('c', strtotime($p['tarih'] ?? 'now'))) . '">';
            $h[] = '<meta property="article:modified_time" content="' . e(date('c', strtotime($p['guncelleme'] ?? 'now'))) . '">';
        }
        if (in_array($gor, ['tekil', 'sayfa'], true) && App::setting('yz_markdown', '1') === '1' && ($this->vars['sablon']['ad'] ?? '') !== '404' && empty($p['noindex'])) {
            $tn = $this->vars['sablon']['ad'] ?? '';
            $mdUrl = $isHome ? App::url('index.md') : ($gor === 'tekil' ? App::url($tn . '/' . ($p['slug'] ?? '') . '.md') : App::url($tn . '.md'));
            $h[] = '<link rel="alternate" type="text/markdown" href="' . e($mdUrl) . '">';
        }
        foreach ($this->alternates() as $lang => $url) {
            $h[] = '<link rel="alternate" hreflang="' . e($lang) . '" href="' . e(App::siteUrl() . $url) . '">';
        }
        if ($this->alternates()) {
            $h[] = '<link rel="alternate" hreflang="x-default" href="' . e(App::siteUrl() . ($this->alternates()[App::defaultLang()] ?? App::url('', App::defaultLang()))) . '">';
        }
        if ($isHome && ($biz = self::localBusiness())) {
            $h[] = '<script type="application/ld+json">' . self::jsonLd($biz) . '</script>';
        }

        $ld = $isHome ? [
            '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site['ad'], 'url' => App::siteUrl() . '/',
            'potentialAction' => ['@type' => 'SearchAction', 'target' => App::siteUrl() . App::url('ara') . '?q={q}', 'query-input' => 'required name=q'],
        ] : null;
        if ($gor === 'tekil') {
            $ld = [
                '@context' => 'https://schema.org', '@type' => $this->vars['sablon']['meta']['schema'] ?? 'Article',
                'headline' => $p['baslik'] ?? '', 'datePublished' => date('c', strtotime($p['tarih'] ?? 'now')),
                'dateModified' => date('c', strtotime($p['guncelleme'] ?? 'now')), 'description' => Str::excerpt($desc, 200),
                'publisher' => ['@type' => 'Organization', 'name' => $site['ad']],
            ] + ($img ? ['image' => $abs($img)] : []);
        }
        if (($this->vars['sablon']['meta']['schema'] ?? '') === 'FAQPage') {
            $qa = [];
            foreach ($p as $v) {
                if (is_array($v) && array_is_list($v)) {
                    foreach ($v as $row) {
                        if (is_array($row) && isset($row['soru'], $row['cevap']) && $row['soru'] !== '') {
                            $qa[] = ['@type' => 'Question', 'name' => strip_tags((string) $row['soru']), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $row['cevap'])]];
                        }
                    }
                }
            }
            $ld = $qa ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $qa] : $ld;
        }
        if ($ld) {
            $h[] = '<script type="application/ld+json">' . self::jsonLd($ld) . '</script>';
        }
        return implode("\n    ", $h) . "\n";
    }

    /**
     * <script type="application/ld+json"> içine güvenle gömülecek JSON.
     * JSON_HEX_TAG: başlık/alan içindeki "</script>" etiketi betik bloğunu kapatamaz (XSS koruması).
     */
    public static function jsonLd(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    public function tag_analitik(array $a): string
    {
        $id = App::setting('google_analitik');
        $html = '';
        $consent = App::setting('cerez_bandi', '1') === '1';
        $type = $consent ? 'text/plain" data-bz-onay="analitik' : 'text/javascript';
        if ($id && preg_match('/^G-[A-Z0-9]+$/', $id)) {
            $html .= '<script type="' . $type . '" data-src="https://www.googletagmanager.com/gtag/js?id=' . e($id) . '"></script>';
            $html .= '<script type="' . $type . '">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config","' . e($id) . '",{anonymize_ip:true});</script>';
        }
        if (($ym = App::setting('yandex_metrika')) && ctype_digit($ym)) {
            $html .= '<script type="' . $type . '">(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");ym(' . $ym . ',"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true});</script>';
        }
        if (($px = App::setting('meta_pixel')) && ctype_digit($px)) {
            $html .= '<script type="' . $type . '">!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","' . $px . '");fbq("track","PageView");</script>';
        }
        // Form dönüşümü: dataLayer + Meta Lead olayı (pazarlama ölçümü)
        if (!empty($this->vars['form']['basarili'])) {
            $html .= '<script>window.dataLayer=window.dataLayer||[];dataLayer.push({event:"bz_form_gonderildi"});if(window.fbq)fbq("track","Lead");if(window.gtag)gtag("event","generate_lead");</script>';
        }
        return $html;
    }

    public function tag_kvkk_bandi(array $a): string
    {
        if (App::setting('cerez_bandi', '1') !== '1') {
            return '';
        }
        $text = $this->tr(App::setting('cerez_metni', 'Sitemizde deneyiminizi iyileştirmek için çerezler kullanıyoruz. Zorunlu çerezler her zaman etkindir; analitik çerezler yalnızca onayınızla çalışır.'));
        $kvkk = App::setting('kvkk_sayfasi', 'kvkk');
        $link = Content::template($kvkk) ? ' <a href="' . e(App::url($kvkk)) . '">' . e($this->tr('Aydınlatma Metni')) . '</a>' : '';
        return <<<HTML
<div id="bz-cerez" role="dialog" aria-live="polite" aria-label="Çerez tercihleri" hidden>
  <p>{$this->esc($text)}{$link}</p>
  <div><button type="button" data-bz-cerez="red">{$this->esc($this->tr('Yalnızca zorunlu'))}</button><button type="button" data-bz-cerez="kabul">{$this->esc($this->tr('Tümünü kabul et'))}</button></div>
</div>
<style>#bz-cerez{position:fixed;left:16px;right:16px;bottom:16px;max-width:560px;margin:auto;background:#111827;color:#f9fafb;padding:18px 20px;border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.3);z-index:99999;font:14px/1.5 system-ui,sans-serif}#bz-cerez a{color:#fca5a5}#bz-cerez div{display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}#bz-cerez button{border:0;border-radius:9px;padding:9px 14px;font:inherit;cursor:pointer;background:#374151;color:#fff}#bz-cerez button[data-bz-cerez=kabul]{background:#dc2626}</style>
<script>(function(){var k='bz_cerez_onay',b=document.getElementById('bz-cerez');function etkinlestir(){document.querySelectorAll('script[data-bz-onay]').forEach(function(s){var n=document.createElement('script');if(s.dataset.src){n.src=s.dataset.src;n.async=true}else{n.text=s.text}document.head.appendChild(n)})}var v=localStorage.getItem(k);if(v==='kabul'){etkinlestir()}else if(!v){b.hidden=false}b.addEventListener('click',function(e){var c=e.target.getAttribute('data-bz-cerez');if(!c)return;localStorage.setItem(k,c);b.hidden=true;if(c==='kabul')etkinlestir()})})();</script>
HTML;
    }

    private function esc(string $s): string
    {
        return e($s);
    }

    public function tag_sayfalama(array $a): string
    {
        $l = $this->lastList;
        if (!$l || $l['sayfa_sayisi'] < 2) {
            return '';
        }
        $q = $_GET;
        unset($q['yol']);
        $link = function (int $n) use ($q) {
            $q['sayfa'] = $n;
            if ($n === 1) {
                unset($q['sayfa']);
            }
            return e(strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($q ? '?' . http_build_query($q) : ''));
        };
        $h = '<nav class="' . e($a['sinif'] ?? 'bz-sayfalama') . '" aria-label="Sayfalama"><ul>';
        if ($l['sayfa'] > 1) {
            $h .= '<li><a href="' . $link($l['sayfa'] - 1) . '" rel="prev">‹ ' . e($this->tr('Önceki')) . '</a></li>';
        }
        for ($i = 1; $i <= $l['sayfa_sayisi']; $i++) {
            if ($i === 1 || $i === $l['sayfa_sayisi'] || abs($i - $l['sayfa']) <= 2) {
                $h .= $i === $l['sayfa'] ? '<li><span aria-current="page">' . $i . '</span></li>' : '<li><a href="' . $link($i) . '">' . $i . '</a></li>';
            } elseif (abs($i - $l['sayfa']) === 3) {
                $h .= '<li><span>…</span></li>';
            }
        }
        if ($l['sayfa'] < $l['sayfa_sayisi']) {
            $h .= '<li><a href="' . $link($l['sayfa'] + 1) . '" rel="next">' . e($this->tr('Sonraki')) . ' ›</a></li>';
        }
        return $h . '</ul></nav>';
    }

    public function tag_ekmek_kirintisi(array $a): string
    {
        $items = [[$this->tr('Ana Sayfa'), App::url('')]];
        $t = $this->vars['sablon'] ?? null;
        if ($t && !in_array($t['ad'], ['ana-sayfa', 'index'], true)) {
            $items[] = [$this->tr($t['meta']['baslik']), App::url($t['ad'])];
        }
        if (($this->vars['gorunum'] ?? '') === 'tekil') {
            $items[] = [$this->vars['sayfa']['baslik'], $this->vars['sayfa']['url']];
        }
        $h = '<nav class="' . e($a['sinif'] ?? 'bz-kirinti') . '" aria-label="Konum"><ol>';
        $ld = [];
        foreach ($items as $i => [$name, $url]) {
            $last = $i === count($items) - 1;
            $h .= '<li>' . ($last ? '<span aria-current="page">' . e($name) . '</span>' : '<a href="' . e($url) . '">' . e($name) . '</a>') . '</li>';
            $ld[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => App::siteUrl() . $url];
        }
        $h .= '</ol></nav>';
        $h .= '<script type="application/ld+json">' . self::jsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ld]) . '</script>';
        return $h;
    }

    public function tag_arama_formu(array $a): string
    {
        return '<form class="' . e($a['sinif'] ?? 'bz-arama') . '" role="search" method="get" action="' . e(App::url('ara')) . '">' .
            (empty(App::$config['guzel_url']) ? '<input type="hidden" name="yol" value="ara">' : '') .
            '<input type="search" name="q" value="' . e($_GET['q'] ?? '') . '" placeholder="' . e($a['yer_tutucu'] ?? $this->tr('Sitede ara…')) . '" aria-label="Ara" required>' .
            '<button type="submit">' . e($a['buton'] ?? $this->tr('Ara')) . '</button></form>';
    }

    public function tag_icindekiler(array $a): string
    {
        $html = (string) $this->resolve($a['alan'] ?? 'icerik');
        if (!preg_match_all('/<(h[23])[^>]*>(.*?)<\/\1>/is', $html, $m, PREG_SET_ORDER)) {
            return '';
        }
        $h = '<nav class="bz-icindekiler" aria-label="' . e($this->tr('İçindekiler')) . '"><strong>' . e($this->tr('İçindekiler')) . '</strong><ul>';
        foreach ($m as $x) {
            $txt = strip_tags($x[2]);
            $h .= '<li class="' . $x[1] . '"><a href="#' . Str::slug($txt, 60) . '">' . e($txt) . '</a></li>';
        }
        return $h . '</ul></nav>';
    }

    public function tag_kvkk_onay(array $a): string
    {
        $kvkk = App::setting('kvkk_sayfasi', 'kvkk');
        $link = e(App::url($kvkk));
        $text = e($a['metin'] ?? $this->tr('Kişisel verilerimin {link}Aydınlatma Metni{/link} kapsamında işlenmesini kabul ediyorum.'));
        $text = str_replace(['{link}', '{/link}'], ['<a href="' . $link . '" target="_blank" rel="noopener">', '</a>'], $text);
        return '<label class="bz-kvkk-onay"><input type="checkbox" name="kvkk_onay" value="1" required> <span>' . $text . '</span></label>';
    }

    /* ------------------------------------------------------------ formlar */

    public function formOpen(array $a): string
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', strtolower($a['ad'] ?? 'iletisim')) ?: 'iletisim';
        $state = Forms::handle($name, $a);
        $this->vars['form'] = $state;
        $this->forms[] = $name;
        $h = '';
        if ($state['basarili']) {
            $h .= '<div class="bz-form-basari" role="status">' . e($a['basari'] ?? $this->tr('Teşekkürler! Mesajınız bize ulaştı.')) . '</div>';
        } elseif ($state['hatalar']) {
            $h .= '<div class="bz-form-hata" role="alert"><ul>';
            foreach ($state['hatalar'] as $err) {
                $h .= '<li>' . e($err) . '</li>';
            }
            $h .= '</ul></div>';
        }
        $h .= '<form method="post" action="#' . e($name) . '" id="' . e($name) . '" class="' . e($a['sinif'] ?? 'bz-form') . '"' .
            (($a['dosya'] ?? '') === 'evet' ? ' enctype="multipart/form-data"' : '') . '>';
        $h .= '<input type="hidden" name="_bz_form" value="' . e($name) . '">';
        $h .= '<input type="hidden" name="_bz_t" value="' . e(Forms::token()) . '">';
        $h .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Web sitesi<input type="text" name="web_sitesi_adresi" tabindex="-1" autocomplete="off"></label></div>';
        // Pazarlama: UTM parametreleri ve giriş sayfası form kaydına eklenir (kampanya başarısı ölçümü)
        $h .= '<input type="hidden" name="_bz_utm" value="">';
        $h .= '<script>(function(f){try{var k="bz_utm",q=new URLSearchParams(location.search),d=JSON.parse(sessionStorage.getItem(k)||"{}");["utm_source","utm_medium","utm_campaign","utm_term","utm_content","gclid","fbclid"].forEach(function(p){if(q.get(p))d[p]=q.get(p).slice(0,100)});if(!d.giris)d.giris=location.pathname;if(!d.yonlendiren&&document.referrer&&document.referrer.indexOf(location.host)<0)d.yonlendiren=document.referrer.slice(0,200);sessionStorage.setItem(k,JSON.stringify(d));f.querySelector("[name=_bz_utm]").value=JSON.stringify(d)}catch(e){}})(document.currentScript.parentNode);</script>';
        return $h;
    }

    public function formClose(): string
    {
        array_pop($this->forms);
        return '</form>';
    }

    /* ------------------------------------------------------------ çok dil */

    private ?array $alts = null;

    /** Geçerli sayfanın diğer dillerdeki adresleri: [dil => url] */
    public function alternates(): array
    {
        if ($this->alts !== null) {
            return $this->alts;
        }
        $this->alts = [];
        if (!App::isMultilingual()) {
            return $this->alts;
        }
        $p = $this->vars['sayfa'] ?? [];
        $tpl = $this->vars['sablon']['ad'] ?? '';
        $gor = $this->vars['gorunum'] ?? 'sayfa';
        if ($gor === 'tekil' && !empty($p['id'])) {
            $row = Content::find((int) $p['id']);
            foreach ($row ? Content::translations($row) : [] as $lang => $r) {
                if ($r['durum'] === 'yayinda') {
                    $this->alts[$lang] = Content::url($r);
                }
            }
        } elseif ($tpl !== '' && $tpl !== '404') {
            $home = in_array($tpl, ['ana-sayfa', 'index'], true);
            $single = !(Content::template($tpl)['meta']['coklu'] ?? false);
            foreach (App::languages() as $lang) {
                if ($single && $lang !== App::defaultLang() && Content::translationPending($tpl, $lang)) {
                    continue; // çevrilmemiş sayfa hreflang'e eklenmez
                }
                $this->alts[$lang] = App::url($home ? '' : $tpl, $lang);
            }
            if (count($this->alts) < 2) {
                $this->alts = [];
            }
        }
        return $this->alts;
    }

    public function tag_dil_secici(array $a): string
    {
        if (!App::isMultilingual()) {
            return '';
        }
        $alts = $this->alternates();
        $h = '<ul class="' . e($a['sinif'] ?? 'bz-diller') . '">';
        foreach (App::languages() as $lang) {
            $url = $alts[$lang] ?? App::url('', $lang);
            $label = ($a['gorunum'] ?? 'kod') === 'ad' ? App::langName($lang) : strtoupper($lang);
            $cur = $lang === App::$lang ? ' aria-current="true" class="aktif"' : '';
            $h .= '<li><a href="' . e($url) . '" hreflang="' . e($lang) . '" lang="' . e($lang) . '"' . $cur . '>' . e($label) . '</a></li>';
        }
        return $h . '</ul>';
    }

    /* ------------------------------------------------------------ arşiv ve kategoriler */

    public function tag_arsiv(array $a): string
    {
        $tpl = $a['sablon'] ?? ($this->vars['sablon']['ad'] ?? '');
        if (!Content::template($tpl)) {
            return '';
        }
        $rows = App::db()->all("SELECT yayin_tarihi FROM bz_icerik WHERE sablon = ? AND dil = ? AND slug != '' AND durum = 'yayinda' AND yayin_tarihi <= ? ORDER BY yayin_tarihi DESC LIMIT 5000", [$tpl, App::$lang, bz_now()]);
        $months = [];
        foreach ($rows as $r) {
            $k = substr($r['yayin_tarihi'], 0, 7);
            $months[$k] = ($months[$k] ?? 0) + 1;
        }
        $base = App::url($tpl);
        $h = '<ul class="' . e($a['sinif'] ?? 'bz-arsiv') . '">';
        foreach ($months as $ym => $n) {
            [$y, $m] = explode('-', $ym);
            $h .= '<li><a href="' . e($base . (str_contains($base, '?') ? '&' : '?') . 'yil=' . $y . '&ay=' . (int) $m) . '">' . e(Str::AYLAR[(int) $m] . ' ' . $y) . '</a> <span>(' . $n . ')</span></li>';
        }
        return $h . '</ul>';
    }

    public function tag_kategoriler(array $a): string
    {
        $tpl = $a['sablon'] ?? ($this->vars['sablon']['ad'] ?? '');
        $field = $a['alan'] ?? 'kategori';
        if (!Content::template($tpl) || !preg_match('/^[a-z0-9_]+$/', $field)) {
            return '';
        }
        $counts = [];
        foreach (Content::query(['sablon' => $tpl, 'limit' => 500])['ogeler'] as $it) {
            foreach ((array) ($it[$field] ?? []) as $v) {
                $v = is_array($v) ? ($v['baslik'] ?? '') : (string) $v;
                if ($v !== '') {
                    $counts[$v] = ($counts[$v] ?? 0) + 1;
                }
            }
        }
        ksort($counts);
        $base = App::url($tpl);
        $h = '<ul class="' . e($a['sinif'] ?? 'bz-kategoriler') . '">';
        foreach ($counts as $v => $n) {
            $active = ($_GET[$field] ?? '') === $v ? ' class="aktif"' : '';
            $h .= '<li><a href="' . e($base . (str_contains($base, '?') ? '&' : '?') . $field . '=' . rawurlencode($v)) . '"' . $active . '>' . e($v) . '</a> <span>(' . $n . ')</span></li>';
        }
        return $h . '</ul>';
    }

    /** <bz:resim alan="kapak" boyutlar="480,800,1200" alt="..." sinif="..." oran="16/9" /> → srcset'li <img> */
    public function tag_resim(array $a): string
    {
        $url = (string) $this->resolve($a['alan'] ?? '');
        if ($url === '') {
            return '';
        }
        $sizes = $a['boyutlar'] ?? '480,800,1200';
        $widths = array_filter(array_map('intval', explode(',', $sizes)));
        $src = Media::thumb($url, (max($widths ?: [1200])) . 'x0', 'sigdir');
        return '<img src="' . e($src) . '" srcset="' . e(Media::srcset($url, $sizes)) . '" sizes="' . e($a['sizes'] ?? '(max-width: 800px) 100vw, 800px') .
            '" alt="' . e($a['alt'] ?? (string) $this->resolve('baslik')) . '" loading="' . e($a['yukleme'] ?? 'lazy') . '" decoding="async"' .
            (isset($a['sinif']) ? ' class="' . e($a['sinif']) . '"' : '') . '>';
    }

    /* ------------------------------------------------------------ İYS ve ödeme */

    public function tag_iys_onay(array $a): string
    {
        $text = e($a['metin'] ?? $this->tr('Ticari elektronik ileti almak istiyorum.'));
        $ch = '';
        foreach (array_filter(array_map('trim', explode(',', $a['kanallar'] ?? ''))) as $k) {
            $ch .= ' <label><input type="checkbox" name="iys_kanal[]" value="' . e($k) . '"> ' . e($k) . '</label>';
        }
        return '<label class="bz-iys-onay"><input type="checkbox" name="iys_onay" value="1"> <span>' . $text . '</span></label>' . ($ch ? '<div class="bz-iys-kanallar">' . $ch . '</div>' : '');
    }

    /** <bz:odeme alan="fiyat" urun="baslik" /> — tutar sunucuda içerikten okunur, istemciden alınmaz. */
    public function tag_odeme(array $a): string
    {
        $p = $this->vars['sayfa'] ?? [];
        if (empty($p['id']) || !Payment::enabled()) {
            return '';
        }
        $price = (string) ($p[$a['alan'] ?? 'fiyat'] ?? '');
        if (!is_numeric($price) || (float) $price <= 0) {
            return '';
        }
        $h = '<form class="' . e($a['sinif'] ?? 'bz-odeme') . '" method="post" action="' . e(App::url('odeme/baslat')) . '">';
        $h .= '<input type="hidden" name="icerik" value="' . (int) $p['id'] . '"><input type="hidden" name="alan" value="' . e($a['alan'] ?? 'fiyat') . '">';
        $h .= '<input type="hidden" name="_bz_t" value="' . e(Forms::token()) . '">';
        $h .= '<p class="bz-odeme-tutar">' . e(Str::tl($price)) . '</p>';
        foreach (['ad_soyad' => 'Ad Soyad', 'eposta' => 'E-posta', 'telefon' => 'Telefon', 'adres' => 'Adres'] as $n => $l) {
            $type = $n === 'eposta' ? 'email' : ($n === 'telefon' ? 'tel' : 'text');
            $h .= '<label>' . e($this->tr($l)) . ' <input type="' . $type . '" name="' . $n . '" required maxlength="200"></label>';
        }
        $h .= $this->tag_kvkk_onay([]);
        return $h . '<button type="submit">' . e($a['buton'] ?? $this->tr('Ödemeye geç')) . '</button></form>';
    }

    /** Ayarlar > İşletme bilgilerinden LocalBusiness şeması */
    public static function localBusiness(): ?array
    {
        $name = App::setting('isletme_adi');
        if (!$name) {
            return null;
        }
        $ld = ['@context' => 'https://schema.org', '@type' => App::setting('isletme_turu', 'LocalBusiness'), 'name' => $name, 'url' => App::siteUrl() . '/'];
        if ($v = App::setting('isletme_telefon')) {
            $ld['telephone'] = $v;
        }
        if ($v = App::setting('isletme_adres')) {
            $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $v, 'addressLocality' => App::setting('isletme_ilce', ''),
                'addressRegion' => App::setting('isletme_il', ''), 'addressCountry' => 'TR'];
        }
        if (($lat = App::setting('isletme_enlem')) && ($lng = App::setting('isletme_boylam'))) {
            $ld['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
        }
        if ($v = App::setting('isletme_saatler')) {
            $ld['openingHours'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $v) ?: [])));
        }
        if ($v = App::setting('logo')) {
            $ld['logo'] = App::siteUrl() . bz_upload_url($v);
        }
        return $ld;
    }
}
