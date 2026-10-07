<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * BOZKURT şablon dili.
 *
 * Tasarımcı, düz HTML'e birkaç <bz:...> etiketi ekler; BOZKURT bu dosyayı tarar
 * (yönetim panelinde alanları otomatik oluşturur) ve hızlı çalışan PHP'ye derler.
 *
 *   <bz:sablon baslik="Blog" coklu="evet" />
 *   <h1><bz:alan ad="baslik" etiket="Başlık">Varsayılan</bz:alan></h1>
 *   {{ sayfa.tarih | tarih:"d F Y" }}   {{{ icerik }}}
 *   <bz:liste sablon="blog" limit="5"> {{ oge.baslik }} <bz:yoksa/> Yazı yok </bz:liste>
 *   <bz:eger kosul="oge.resim"> ... <bz:degilse/> ... </bz:eger>
 *   <bz:tekrar ad="galeri"> <bz:alan ad="foto" tur="resim"/> </bz:tekrar>
 *   <bz:dahil dosya="parcalar/ust.html" />
 */
final class Template
{
    private const TOKEN_RE = '/<bz:([a-z][a-z0-9\-]*)((?:\s+[a-z_][a-z0-9_\-]*\s*=\s*"[^"]*")*)\s*(\/?)>|<\/bz:([a-z][a-z0-9\-]*)\s*>/i';
    private const ATTR_RE = '/([a-z_][a-z0-9_\-]*)\s*=\s*"([^"]*)"/i';

    public const FIELD_TYPES = [
        'metin' => 'Tek satır metin', 'uzunmetin' => 'Çok satırlı metin', 'zengin' => 'Zengin metin (editör)',
        'resim' => 'Resim', 'dosya' => 'Dosya', 'tarih' => 'Tarih', 'tarihsaat' => 'Tarih ve saat',
        'sayi' => 'Sayı', 'fiyat' => 'Fiyat (₺)', 'secim' => 'Açılır liste', 'coklusecim' => 'Çoklu seçim',
        'onay' => 'Onay kutusu', 'eposta' => 'E-posta', 'url' => 'Bağlantı', 'telefon' => 'Telefon',
        'renk' => 'Renk', 'iliski' => 'İlişki (başka şablon)', 'video' => 'Video (YouTube/Vimeo)',
        'harita' => 'Harita (adres)', 'tekrar' => 'Tekrarlanan grup', 'bloklar' => 'Blok düzenleyici',
        'markdown' => 'Markdown', 'il' => 'İl (81 il)', 'tckn' => 'T.C. kimlik no', 'vkn' => 'Vergi kimlik no', 'iban' => 'IBAN',
    ];

    private int $counter = 0;
    private array $stack = [];

    /* ---------------------------------------------------------------- tarayıcı */

    public static function path(string $name): string
    {
        $name = str_replace(['\\', "\0"], '', $name);
        if (str_contains($name, '..') || !preg_match('#^[a-z0-9_\-/]+(\.html)?$#i', $name)) {
            throw new \RuntimeException("Geçersiz şablon adı: $name");
        }
        if (!str_ends_with($name, '.html')) {
            $name .= '.html';
        }
        return BZ_TEMPLATES . '/' . $name;
    }

    /** dahil etiketlerini çözerek tek kaynak metin üretir */
    public static function source(string $name, int $depth = 0): string
    {
        $file = self::path($name);
        if (!is_file($file)) {
            return "<!-- bz: dosya bulunamadı: $name -->";
        }
        if ($depth > 10) {
            return '<!-- bz: dahil derinliği aşıldı -->';
        }
        $src = (string) file_get_contents($file);
        return preg_replace_callback('/<bz:dahil\s+dosya\s*=\s*"([^"]+)"\s*\/?>/i',
            fn($m) => self::source($m[1], $depth + 1), $src) ?? $src;
    }

    public static function attrs(string $s): array
    {
        preg_match_all(self::ATTR_RE, $s, $m, PREG_SET_ORDER);
        $a = [];
        foreach ($m as $x) {
            $a[strtolower($x[1])] = html_entity_decode($x[2], ENT_QUOTES, 'UTF-8');
        }
        return $a;
    }

    /** @return array{meta:array, alanlar:array, genel:array} */
    public static function scan(string $name): array
    {
        $src = self::source($name);
        preg_match_all(self::TOKEN_RE, $src, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $meta = [];
        $fields = [];
        $globals = [];
        $repeat = null; // açık tekrar alanının adı
        $blocks = null; // açık bloklar alanı
        $block = null;  // açık blok tipi

        foreach ($tokens as $i => $tk) {
            $full = $tk[0][0];
            $closing = !empty($tk[4][0]);
            $tag = strtolower($closing ? $tk[4][0] : $tk[1][0]);
            $self = !$closing && ($tk[3][0] ?? '') === '/';
            if ($closing) {
                if ($tag === 'tekrar') {
                    $repeat = null;
                } elseif ($tag === 'blok') {
                    $block = null;
                } elseif ($tag === 'bloklar') {
                    $blocks = null;
                }
                continue;
            }
            $a = self::attrs($tk[2][0] ?? '');
            if ($tag === 'sablon') {
                $meta = $a;
            } elseif ($tag === 'bloklar') {
                $ad = $a['ad'] ?? '';
                if (preg_match('/^[a-z][a-z0-9_]*$/', $ad)) {
                    $fields[$ad] = self::fieldDef($a, 'bloklar') + ['bloklar' => []];
                    $blocks = $self ? null : $ad;
                }
            } elseif ($tag === 'blok' && $blocks !== null) {
                $tip = $a['ad'] ?? '';
                if (preg_match('/^[a-z][a-z0-9_]*$/', $tip)) {
                    $fields[$blocks]['bloklar'][$tip] = ['etiket' => $a['etiket'] ?? Str::title(str_replace('_', ' ', $tip)), 'alt' => []];
                    $block = $self ? null : $tip;
                }
            } elseif ($tag === 'alan' && $blocks !== null && $block !== null) {
                $ad = $a['ad'] ?? '';
                if (preg_match('/^[a-z][a-z0-9_]*$/', $ad)) {
                    $def = self::fieldDef($a, null);
                    if (!$self) {
                        $start = $tk[0][1] + strlen($full);
                        $end = stripos($src, '</bz:alan>', $start);
                        if ($end !== false) {
                            $def['varsayilan'] = trim(substr($src, $start, $end - $start));
                        }
                    }
                    $fields[$blocks]['bloklar'][$block]['alt'][$ad] = $def;
                }
            } elseif ($tag === 'alan' || $tag === 'genel' || $tag === 'tekrar') {
                $ad = $a['ad'] ?? '';
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $ad)) {
                    continue;
                }
                $def = self::fieldDef($a, $tag === 'tekrar' ? 'tekrar' : null);
                if (!$self && $tag !== 'tekrar') {
                    // varsayılan değer: kapanış etiketine kadarki içerik
                    $start = $tk[0][1] + strlen($full);
                    $end = stripos($src, '</bz:' . $tag . '>', $start);
                    if ($end !== false) {
                        $def['varsayilan'] = trim(substr($src, $start, $end - $start));
                    }
                }
                if ($tag === 'genel') {
                    $globals[$ad] = $def;
                } elseif ($tag === 'tekrar') {
                    $def['alt'] = [];
                    $fields[$ad] = $def;
                    if (!$self) {
                        $repeat = $ad;
                    }
                } elseif ($repeat !== null) {
                    $fields[$repeat]['alt'][$ad] = $def;
                } elseif (!isset($fields[$ad])) {
                    $fields[$ad] = $def;
                }
            }
        }
        $meta += ['baslik' => Str::title(str_replace(['-', '_'], ' ', basename($name, '.html'))), 'coklu' => 'hayir'];
        $meta['coklu'] = in_array(strtolower($meta['coklu']), ['evet', 'true', '1', 'yes'], true);
        $meta['menude'] = in_array(strtolower($meta['menude'] ?? 'evet'), ['evet', 'true', '1', 'yes'], true);
        $meta['sira'] = (int) ($meta['sira'] ?? 50);
        $meta['ikon'] ??= $meta['coklu'] ? 'yigin' : 'sayfa';
        return ['meta' => $meta, 'alanlar' => $fields, 'genel' => $globals];
    }

    private static function fieldDef(array $a, ?string $forceType): array
    {
        $tur = $forceType ?? strtolower($a['tur'] ?? 'metin');
        if (!isset(self::FIELD_TYPES[$tur])) {
            $tur = 'metin';
        }
        return [
            'ad' => $a['ad'],
            'tur' => $tur,
            'etiket' => $a['etiket'] ?? Str::title(str_replace('_', ' ', $a['ad'])),
            'yardim' => $a['yardim'] ?? '',
            'zorunlu' => in_array(strtolower($a['zorunlu'] ?? ''), ['evet', 'true', '1'], true),
            'secenekler' => isset($a['secenekler']) ? array_map('trim', explode(',', $a['secenekler'])) : [],
            'sablon' => $a['sablon'] ?? '',
            'grup' => $a['grup'] ?? 'İçerik',
            'yer_tutucu' => $a['yer_tutucu'] ?? '',
            'en_fazla' => (int) ($a['en_fazla'] ?? 0),
            'genislik' => $a['genislik'] ?? 'tam',
            'aranabilir' => !in_array(strtolower($a['aranabilir'] ?? 'evet'), ['hayir', 'false', '0'], true),
            'api' => !in_array(strtolower($a['api'] ?? 'evet'), ['hayir', 'false', '0'], true),
            'cevrilebilir' => !in_array(strtolower($a['cevrilebilir'] ?? 'evet'), ['hayir', 'false', '0'], true),
            'varsayilan' => '',
        ];
    }

    /* ---------------------------------------------------------------- derleyici */

    public static function compiled(string $name): string
    {
        $dir = BZ_CACHE . '/derlenmis';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // Dosya adı şablon kümesinin imzasını içerir: FTP ile eski tarihli dosya yüklense bile yeniden derlenir
        $stem = str_replace('/', '__', preg_replace('/\.html$/i', '', $name) ?? $name);
        $target = $dir . '/' . $stem . '_' . substr(md5($name . self::signature()), 0, 16) . '.php';
        if (!is_file($target)) {
            foreach (glob($dir . '/' . $stem . '_*.php') ?: [] as $old) {
                @unlink($old);
            }
            $php = (new self())->compile(self::source($name));
            file_put_contents($target, $php, LOCK_EX);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($target, true);
            }
        }
        return $target;
    }

    /** Şablon klasörünün imzası (dosya yolu + boyut + değişiklik zamanı) — mtime geriye gitse de değişikliği yakalar */
    public static function signature(): string
    {
        static $sig = null;
        if ($sig === null) {
            $parts = [filemtime(__FILE__) . BZ_VERSION];
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BZ_TEMPLATES, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $parts[] = $f->getPathname() . ':' . $f->getSize() . ':' . $f->getMTime();
            }
            sort($parts);
            $sig = md5(implode('|', $parts));
        }
        return $sig;
    }

    public static function templatesMtime(): int
    {
        static $m = null;
        if ($m === null) {
            $m = 0;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BZ_TEMPLATES, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $m = max($m, $f->getMTime());
            }
            $m = max($m, filemtime(__FILE__));
        }
        return $m;
    }

    public function compile(string $src): string
    {
        $out = "<?php /* BOZKURT CMS derlenmiş şablon — elle düzenlemeyin */ ?>";
        $pos = 0;
        $skipUntil = null; // alan varsayılan içeriği atla
        preg_match_all(self::TOKEN_RE, $src, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($tokens as $tk) {
            $full = $tk[0][0];
            $off = $tk[0][1];
            $closing = !empty($tk[4][0]);
            $tag = strtolower($closing ? $tk[4][0] : $tk[1][0]);
            $self = !$closing && ($tk[3][0] ?? '') === '/';

            if ($skipUntil !== null) {
                if ($closing && $tag === $skipUntil) {
                    $skipUntil = null;
                    $pos = $off + strlen($full);
                }
                continue;
            }
            $out .= $this->text(substr($src, $pos, $off - $pos));
            $pos = $off + strlen($full);
            $a = $closing ? [] : self::attrs($tk[2][0] ?? '');

            if ($closing) {
                $out .= $this->close($tag);
                continue;
            }
            switch ($tag) {
                case 'sablon':
                    break;
                case 'alan':
                case 'genel':
                    if (!$self) {
                        $skipUntil = $tag;
                    }
                    if (strtolower($a['goster'] ?? 'evet') !== 'hayir') {
                        $fn = $tag === 'alan' ? 'field' : 'global';
                        $out .= '<?php echo $R->' . $fn . '(' . var_export($a['ad'] ?? '', true) . '); ?>';
                    }
                    break;
                case 'tekrar':
                    if ($self) {
                        break;
                    }
                    $v = '$__t' . (++$this->counter);
                    $this->stack[] = ['tag' => 'tekrar'];
                    $out .= "<?php foreach (\$R->rows(" . var_export($a['ad'] ?? '', true) . ") as $v) { \$R->pushRow($v); ?>";
                    break;
                case 'bloklar':
                    if ($self) {
                        break;
                    }
                    $v = '$__b' . (++$this->counter);
                    $this->stack[] = ['tag' => 'bloklar'];
                    $out .= "<?php foreach (\$R->rows(" . var_export($a['ad'] ?? '', true) . ") as $v) { \$R->pushRow($v); ?>";
                    break;
                case 'blok':
                    if ($self) {
                        break;
                    }
                    $this->stack[] = ['tag' => 'blok'];
                    $out .= '<?php if ($R->blockIs(' . var_export($a['ad'] ?? '', true) . ')) { ?>';
                    break;
                case 'liste':
                    $v = '$__l' . (++$this->counter);
                    $this->stack[] = ['tag' => 'liste', 'var' => $v, 'yoksa' => false];
                    $out .= "<?php $v = \$R->liste(" . var_export($a, true) . "); foreach ({$v}['ogeler'] as \$__i => \$__o) { \$R->push(['oge' => \$__o, 'sira' => \$__i + 1, 'ilk' => \$__i === 0, 'son' => \$__i === count({$v}['ogeler']) - 1]); ?>";
                    if ($self) {
                        $out .= $this->close('liste');
                    }
                    break;
                case 'yoksa':
                    $top = end($this->stack);
                    if ($top && $top['tag'] === 'liste' && !$top['yoksa']) {
                        $this->stack[count($this->stack) - 1]['yoksa'] = true;
                        $out .= "<?php \$R->pop(); } if (!{$top['var']}['ogeler']) { ?>";
                    }
                    break;
                case 'eger':
                    $this->stack[] = ['tag' => 'eger'];
                    $out .= '<?php if ($R->kosul(' . var_export($a['kosul'] ?? '', true) . ')) { ?>';
                    break;
                case 'yoksa-eger':
                case 'degilse':
                    // Yalnızca doğrudan <bz:eger> içinde geçerlidir; aksi hâlde derlenmiş PHP bozulurdu
                    $top = end($this->stack);
                    if (!$top || $top['tag'] !== 'eger') {
                        $out .= '<!-- bz: <bz:' . $tag . '> yalnızca <bz:eger> içinde kullanılabilir -->';
                    } elseif ($tag === 'degilse') {
                        $out .= '<?php } else { ?>';
                    } else {
                        $out .= '<?php } elseif ($R->kosul(' . var_export($a['kosul'] ?? '', true) . ')) { ?>';
                    }
                    break;
                case 'form':
                    $this->stack[] = ['tag' => 'form'];
                    $out .= '<?php echo $R->formOpen(' . var_export($a, true) . '); ?>';
                    break;
                case 'ayarla':
                    $out .= '<?php $R->set(' . var_export($a['ad'] ?? '', true) . ', ' . var_export($a['deger'] ?? '', true) . '); ?>';
                    break;
                case 'menu': case 'seo': case 'kvkk-bandi': case 'kvkk-onay': case 'sayfalama':
                case 'ekmek-kirintisi': case 'arama-formu': case 'analitik': case 'icindekiler':
                case 'dil-secici': case 'arsiv': case 'kategoriler': case 'odeme': case 'iys-onay': case 'resim':
                    $out .= '<?php echo $R->tag_' . str_replace('-', '_', $tag) . '(' . var_export($a, true) . '); ?>';
                    break;
                default:
                    $out .= '<!-- bz: bilinmeyen etiket "' . htmlspecialchars($tag) . '" -->';
            }
        }
        $out .= $this->text(substr($src, $pos));
        while ($this->stack) {
            $out .= $this->close(end($this->stack)['tag']);
        }
        return $out;
    }

    private function close(string $tag): string
    {
        $top = end($this->stack);
        if (!$top || $top['tag'] !== $tag) {
            return in_array($tag, ['alan', 'genel'], true) ? '' : "<!-- bz: eşleşmeyen kapanış </bz:$tag> -->";
        }
        array_pop($this->stack);
        return match ($tag) {
            'tekrar', 'bloklar' => '<?php $R->popRow(); } ?>',
            'blok' => '<?php } ?>',
            'liste' => $top['yoksa'] ? '<?php } ?>' : '<?php $R->pop(); } ?>',
            'eger' => '<?php } ?>',
            'form' => '<?php echo $R->formClose(); ?>',
            default => '',
        };
    }

    /** Düz metin: {{ değişken | süzgeç:"arg" }} ve {{{ ham }}} */
    private function text(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $s = str_replace('<?', '<?php echo "<?"; ?>', $s);
        return preg_replace_callback('/\{\{\{\s*(.+?)\s*\}\}\}|\{\{\s*(.+?)\s*\}\}/s', function ($m) {
            $raw = isset($m[1]) && $m[1] !== '';
            [$path, $filters] = self::parseExpr($raw ? $m[1] : $m[2]);
            return '<?php echo $R->out(' . var_export($path, true) . ', ' . var_export($filters, true) . ', ' . ($raw ? 'false' : 'true') . '); ?>';
        }, $s) ?? $s;
    }

    public static function parseExpr(string $expr): array
    {
        $parts = preg_split('/\|(?=(?:[^"]*"[^"]*")*[^"]*$)/', $expr) ?: [];
        $path = trim(array_shift($parts) ?? '');
        $filters = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (!preg_match('/^([a-z_]+)\s*(?::\s*(.*))?$/is', $p, $fm)) {
                continue;
            }
            $args = [];
            if (isset($fm[2]) && $fm[2] !== '') {
                preg_match_all('/"([^"]*)"|([^,\s]+)/', $fm[2], $am, PREG_SET_ORDER);
                foreach ($am as $x) {
                    $args[] = ($x[1] ?? '') !== '' || ($x[2] ?? '') === '' ? $x[1] : $x[2];
                }
            }
            $filters[] = [strtolower($fm[1]), $args];
        }
        return [$path, $filters];
    }

    /** Tasarımcı için şablon denetimi: bilinmeyen/eşleşmeyen etiketler, geçersiz alan adları, yinelenen alanlar */
    public static function lint(string $name): array
    {
        $issues = [];
        $known = ['sablon', 'alan', 'genel', 'tekrar', 'bloklar', 'blok', 'liste', 'yoksa', 'eger', 'yoksa-eger', 'degilse', 'form', 'ayarla', 'dahil',
            'menu', 'seo', 'kvkk-bandi', 'kvkk-onay', 'sayfalama', 'ekmek-kirintisi', 'arama-formu', 'analitik', 'icindekiler', 'dil-secici', 'arsiv',
            'kategoriler', 'odeme', 'iys-onay', 'resim'];
        $blockTags = ['tekrar', 'bloklar', 'blok', 'liste', 'eger', 'form'];
        $src = self::source($name);
        if (str_contains($src, '<!-- bz: dosya bulunamadı')) {
            preg_match_all('/<!-- bz: dosya bulunamadı: ([^ ]+) -->/', $src, $m);
            foreach ($m[1] as $f) {
                $issues[] = "Dahil edilen dosya bulunamadı: $f";
            }
        }
        preg_match_all(self::TOKEN_RE, $src, $tokens, PREG_SET_ORDER);
        $stack = [];
        foreach ($tokens as $tk) {
            $closing = !empty($tk[4]);
            $tag = strtolower($closing ? $tk[4] : $tk[1]);
            $self = !$closing && ($tk[3] ?? '') === '/';
            if (!in_array($tag, $known, true)) {
                $issues[] = "Bilinmeyen etiket: <bz:$tag>";
                continue;
            }
            if ($closing) {
                if (in_array($tag, ['alan', 'genel'], true)) {
                    continue;
                }
                $top = array_pop($stack);
                if ($top !== $tag) {
                    $issues[] = "Eşleşmeyen kapanış: </bz:$tag>" . ($top ? " (beklenen </bz:$top>)" : '');
                    if ($top) {
                        $stack[] = $top;
                    }
                }
            } elseif (!$self && in_array($tag, $blockTags, true)) {
                $stack[] = $tag;
            } elseif (in_array($tag, ['degilse', 'yoksa-eger'], true) && end($stack) !== 'eger') {
                $issues[] = "<bz:$tag> yalnızca <bz:eger> içinde kullanılabilir";
            } elseif ($tag === 'yoksa' && end($stack) !== 'liste') {
                $issues[] = '<bz:yoksa> yalnızca <bz:liste> içinde kullanılabilir';
            }
            if (!$closing && in_array($tag, ['alan', 'genel', 'tekrar', 'bloklar'], true)) {
                $a = self::attrs($tk[2] ?? '');
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $a['ad'] ?? '')) {
                    $issues[] = "<bz:$tag> için geçersiz ad: “" . ($a['ad'] ?? '') . '” (küçük harf, rakam ve _ kullanın)';
                }
                if (isset($a['tur']) && !isset(self::FIELD_TYPES[strtolower($a['tur'])])) {
                    $issues[] = "Bilinmeyen alan türü: “{$a['tur']}” (" . ($a['ad'] ?? '') . ')';
                }
            }
        }
        foreach ($stack as $open) {
            $issues[] = "Kapatılmamış etiket: <bz:$open>";
        }
        return array_values(array_unique($issues));
    }
}
