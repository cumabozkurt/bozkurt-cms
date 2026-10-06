<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Şablon kayıt defteri ve içerik deposu.
 */
final class Content
{
    private static ?array $templates = null;
    private static array $globalsCache = [];

    /** Yönetimde görünen şablonlar: "_" ile başlamayan .html dosyaları (alt klasörler hariç). */
    public static function templates(): array
    {
        if (self::$templates !== null) {
            return self::$templates;
        }
        $cacheFile = BZ_CACHE . '/sablonlar.json';
        $sig = Template::signature();
        if (is_file($cacheFile)) {
            $data = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($data) && ($data['__imza'] ?? '') === $sig) {
                unset($data['__imza']);
                return self::$templates = $data;
            }
        }
        $list = [];
        foreach (glob(BZ_TEMPLATES . '/*.html') ?: [] as $file) {
            $name = basename($file, '.html');
            if ($name[0] === '_' || $name === '404') {
                continue;
            }
            $s = Template::scan($name);
            $list[$name] = ['ad' => $name] + $s;
        }
        uasort($list, fn($a, $b) => [$a['meta']['sira'], $a['meta']['baslik']] <=> [$b['meta']['sira'], $b['meta']['baslik']]);
        if (!is_dir(BZ_CACHE)) {
            @mkdir(BZ_CACHE, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode($list + ['__imza' => $sig], JSON_UNESCAPED_UNICODE), LOCK_EX);
        return self::$templates = $list;
    }

    public static function template(string $name): ?array
    {
        return self::templates()[$name] ?? null;
    }

    /** Tüm şablonlarda ve parçalarda tanımlanan genel (site geneli) alanlar */
    public static function globalDefs(): array
    {
        $defs = [];
        foreach (self::templates() as $t) {
            $defs += $t['genel'];
        }
        foreach (glob(BZ_TEMPLATES . '/{_*.html,404.html}', GLOB_BRACE) ?: [] as $f) {
            $defs += Template::scan(basename($f, '.html'))['genel'];
        }
        return $defs;
    }

    /** Genel alan değeri; varsayılan olmayan dillerde "en:telefon" anahtarı, boşsa varsayılan dile düşer. */
    public static function globalValue(string $key, ?string $lang = null): mixed
    {
        if (!self::$globalsCache) {
            foreach (App::db()->all('SELECT anahtar, deger FROM bz_genel') as $r) {
                self::$globalsCache[$r['anahtar']] = json_decode((string) $r['deger'], true);
            }
            self::$globalsCache['__yuklendi'] = true;
        }
        $lang ??= App::$lang;
        if ($lang !== App::defaultLang()) {
            $v = self::$globalsCache[$lang . ':' . $key] ?? null;
            if ($v !== null && $v !== '' && $v !== []) {
                return $v;
            }
        }
        return self::$globalsCache[$key] ?? null;
    }

    public static function globalKey(string $key, string $lang): string
    {
        return $lang === App::defaultLang() ? $key : $lang . ':' . $key;
    }

    public static function clearGlobalsCache(): void
    {
        self::$globalsCache = [];
    }

    /* ------------------------------------------------------------ içerik */

    public static function find(int $id): ?array
    {
        return App::db()->one('SELECT * FROM bz_icerik WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $tpl, string $slug, bool $publishedOnly = true, ?string $lang = null): ?array
    {
        $sql = 'SELECT * FROM bz_icerik WHERE sablon = ? AND slug = ? AND dil = ?';
        $p = [$tpl, $slug, $lang ?? App::$lang];
        if ($publishedOnly) {
            $sql .= " AND durum = 'yayinda' AND yayin_tarihi <= ?";
            $p[] = bz_now();
        }
        return App::db()->one($sql, $p);
    }

    /** Tekil (çoklu olmayan) şablonun tek kaydı; yoksa varsayılanlarla oluşturulur. */
    public static function single(string $tpl, ?string $lang = null): array
    {
        $lang ??= App::$lang;
        $row = App::db()->one("SELECT * FROM bz_icerik WHERE sablon = ? AND slug = '' AND dil = ?", [$tpl, $lang]);
        if ($row) {
            return $row;
        }
        $t = self::template($tpl);
        $default = $lang !== App::defaultLang() ? self::single($tpl, App::defaultLang()) : null;
        if ($default) {
            // Çeviri yoksa varsayılan dilin içeriği kopyalanır (sayfa boş görünmez)
            $veri = json_decode((string) $default['veri'], true) ?: [];
        } else {
            $veri = [];
            foreach ($t['alanlar'] ?? [] as $ad => $def) {
                $veri[$ad] = in_array($def['tur'], ['tekrar', 'bloklar'], true) ? [] : $def['varsayilan'];
            }
        }
        $id = App::db()->insert('bz_icerik', [
            'sablon' => $tpl, 'baslik' => $default['baslik'] ?? ($t['meta']['baslik'] ?? $tpl), 'slug' => '', 'durum' => 'yayinda',
            'yayin_tarihi' => bz_now(), 'veri' => json_encode($veri, JSON_UNESCAPED_UNICODE), 'seo' => $default ? '{"ceviri_bekliyor":true}' : '{}',
            'dil' => $lang, 'grup' => (int) ($default['id'] ?? 0),
            'olusturma' => bz_now(), 'guncelleme' => bz_now(),
        ]);
        if (!$default) {
            App::db()->update('bz_icerik', ['grup' => $id], 'id = ?', [$id]);
        }
        return self::find($id);
    }

    /** Tekil sayfanın bu dildeki çevirisi henüz kaydedilmedi mi? (Yoksa da "bekliyor" sayılır) */
    public static function translationPending(string $tpl, string $lang): bool
    {
        $seo = App::db()->val("SELECT seo FROM bz_icerik WHERE sablon = ? AND slug = '' AND dil = ?", [$tpl, $lang]);
        if ($seo === null || $seo === false) {
            return true;
        }
        return !empty((json_decode((string) $seo, true) ?: [])['ceviri_bekliyor']);
    }

    /** Bir kaydın tüm dillerdeki karşılıkları: [dil => satır] */
    public static function translations(array $row): array
    {
        $g = (int) ($row['grup'] ?? 0) ?: (int) $row['id'];
        $out = [];
        foreach (App::db()->all('SELECT * FROM bz_icerik WHERE (grup = ? OR id = ?) AND sablon = ?', [$g, $g, $row['sablon']]) as $r) {
            if (in_array($r['dil'], App::languages(), true)) {
                $out[$r['dil']] = $r;
            }
        }
        return $out;
    }

    public static function uniqueSlug(string $tpl, string $slug, int $exceptId = 0, ?string $lang = null): string
    {
        $base = $slug !== '' ? $slug : 'icerik';
        $slug = $base;
        $i = 2;
        while (App::db()->val('SELECT COUNT(*) FROM bz_icerik WHERE sablon = ? AND slug = ? AND id != ? AND dil = ?', [$tpl, $slug, $exceptId, $lang ?? App::$lang])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function url(array $row): string
    {
        $tpl = $row['sablon'];
        $lang = $row['dil'] ?? App::$lang;
        if ($row['slug'] === '') {
            return App::url(in_array($tpl, ['ana-sayfa', 'index'], true) ? '' : $tpl, $lang);
        }
        return App::url($tpl . '/' . $row['slug'], $lang);
    }

    /** Veritabanı satırını şablonda kullanılacak diziye çevirir. */
    public static function hydrate(array $row, int $depth = 0): array
    {
        $veri = json_decode((string) ($row['veri'] ?? '{}'), true) ?: [];
        $seo = json_decode((string) ($row['seo'] ?? '{}'), true) ?: [];
        $t = self::template($row['sablon']);
        $out = [
            'id' => (int) $row['id'],
            'baslik' => $row['baslik'],
            'slug' => $row['slug'],
            'url' => self::url($row),
            'tam_url' => App::siteUrl() . self::url($row),
            'tarih' => $row['yayin_tarihi'],
            'guncelleme' => $row['guncelleme'],
            'sablon' => $row['sablon'],
            'durum' => $row['durum'],
            'dil' => $row['dil'] ?? App::defaultLang(),
            'grup' => (int) ($row['grup'] ?? 0),
            'seo_baslik' => $seo['baslik'] ?? '',
            'seo_aciklama' => $seo['aciklama'] ?? '',
            'seo_resim' => bz_upload_url($seo['resim'] ?? ''),
            'noindex' => !empty($seo['noindex']) || !empty($seo['ceviri_bekliyor']),
            'ceviri_bekliyor' => !empty($seo['ceviri_bekliyor']),
        ];
        foreach ($t['alanlar'] ?? [] as $ad => $def) {
            $out[$ad] = self::presentValue($def, $veri[$ad] ?? $def['varsayilan'], $depth);
        }
        // ilk zengin/uzun metin alanından otomatik özet ve okuma süresi
        foreach ($t['alanlar'] ?? [] as $ad => $def) {
            if (in_array($def['tur'], ['zengin', 'uzunmetin'], true) && is_string($out[$ad])) {
                $out['ozet'] ??= Str::excerpt($out[$ad], 180);
                $out['okuma_suresi'] ??= Str::readingTime($out[$ad]);
            }
        }
        $out['ozet'] ??= '';
        return $out;
    }

    public static function presentValue(array $def, mixed $v, int $depth = 0): mixed
    {
        switch ($def['tur']) {
            case 'resim':
            case 'dosya':
                return bz_upload_url(is_string($v) ? $v : '');
            case 'onay':
                return (bool) $v;
            case 'coklusecim':
                return is_array($v) ? $v : array_filter(array_map('trim', explode(',', (string) $v)));
            case 'tekrar':
                $rows = [];
                foreach (is_array($v) ? $v : [] as $r) {
                    $row = [];
                    foreach ($def['alt'] ?? [] as $sad => $sdef) {
                        $row[$sad] = self::presentValue($sdef, $r[$sad] ?? '', $depth);
                    }
                    $rows[] = $row;
                }
                return $rows;
            case 'bloklar':
                $rows = [];
                foreach (is_array($v) ? $v : [] as $r) {
                    $tip = (string) ($r['_tip'] ?? '');
                    if (!isset($def['bloklar'][$tip])) {
                        continue;
                    }
                    $row = ['_tip' => $tip];
                    foreach ($def['bloklar'][$tip]['alt'] ?? [] as $sad => $sdef) {
                        $row[$sad] = self::presentValue($sdef, $r[$sad] ?? '', $depth);
                    }
                    $rows[] = $row;
                }
                return $rows;
            case 'iliski':
                if (!$v || $depth > 1) {
                    return null;
                }
                $r = self::find((int) $v);
                return $r ? self::hydrate($r, $depth + 1) : null;
            case 'video':
                return is_string($v) ? $v : '';
            case 'zengin':
                return Runtime::headingIds(is_string($v) ? $v : '');
            case 'markdown':
                return Runtime::headingIds(Markdown::toHtml(is_string($v) ? $v : ''));
            default:
                return is_scalar($v) ? (string) $v : '';
        }
    }

    /**
     * Liste sorgusu. Seçenekler: sablon, limit, sayfa, sirala, yon, filtre ("alan=deger;alan2!=x"),
     * arama, haric (id), sadece_yayinda.
     */
    public static function query(array $o): array
    {
        $tpl = $o['sablon'];
        $limit = max(1, min(500, (int) ($o['limit'] ?? 10)));
        $page = max(1, (int) ($o['sayfa'] ?? 1));
        $sort = $o['sirala'] ?? 'tarih';
        $dir = in_array(strtolower($o['yon'] ?? ''), ['artan', 'asc'], true) ? 'ASC' : 'DESC';
        $sysCols = ['tarih' => 'yayin_tarihi', 'baslik' => 'baslik', 'sira' => 'sira', 'guncelleme' => 'guncelleme', 'id' => 'id'];

        if ($tpl === '*') {
            $multi = array_keys(array_filter(self::templates(), fn($t) => $t['meta']['coklu']));
            $multi = $multi ?: ['__yok__'];
            $where = 'sablon IN (' . implode(',', array_fill(0, count($multi), '?')) . ") AND slug != ''";
            $params = $multi;
        } else {
            $where = "sablon = ? AND slug != ''";
            $params = [$tpl];
        }
        $where .= ' AND dil = ?';
        $params[] = $o['dil'] ?? App::$lang;
        if (!empty($o['yil']) && ctype_digit((string) $o['yil'])) {
            $from = sprintf('%04d-%02d-01 00:00:00', (int) $o['yil'], max(1, (int) ($o['ay'] ?? 1)));
            $to = !empty($o['ay']) ? date('Y-m-d H:i:s', strtotime($from . ' +1 month')) : sprintf('%04d-01-01 00:00:00', (int) $o['yil'] + 1);
            $where .= ' AND yayin_tarihi >= ? AND yayin_tarihi < ?';
            array_push($params, $from, $to);
        }
        if (!isset($o['sadece_yayinda']) || $o['sadece_yayinda']) {
            $where .= " AND durum = 'yayinda' AND yayin_tarihi <= ?";
            $params[] = bz_now();
        }
        if (!empty($o['haric'])) {
            $where .= ' AND id != ?';
            $params[] = (int) $o['haric'];
        }
        $search = trim((string) ($o['arama'] ?? ''));
        $filters = self::parseFilters((string) ($o['filtre'] ?? ''));
        $db = App::db();

        if ($sort === 'rastgele') {
            $order = $db->driver === 'mysql' ? 'RAND()' : 'RANDOM()';
        } else {
            $order = ($sysCols[$sort] ?? null) ? $sysCols[$sort] . " $dir, id $dir" : null;
        }

        if ($order && !$filters && $search === '') {
            $total = (int) $db->val("SELECT COUNT(*) FROM bz_icerik WHERE $where", $params);
            $rows = $db->all("SELECT * FROM bz_icerik WHERE $where ORDER BY $order LIMIT $limit OFFSET " . (($page - 1) * $limit), $params);
            $items = array_map([self::class, 'hydrate'], $rows);
        } else {
            // alan değerine göre süzme/sıralama/arama: PHP tarafında (küçük-orta siteler için yeterince hızlı)
            // Güvenlik sınırı: alan bazlı süzme en fazla 5.000 kayıt üzerinde çalışır (DoS koruması)
            $rows = $db->all("SELECT * FROM bz_icerik WHERE $where ORDER BY " . ($order ?? "yayin_tarihi $dir") . ' LIMIT 5000', $params);
            $items = array_map([self::class, 'hydrate'], $rows);
            if ($search !== '') {
                $needle = Str::lower($search);
                $items = array_values(array_filter($items, function ($it) use ($needle) {
                    $hay = $it['baslik'];
                    foreach (self::template($it['sablon'])['alanlar'] ?? [] as $ad => $def) {
                        if ($def['aranabilir'] && is_string($it[$ad] ?? null) && !in_array($def['tur'], ['resim', 'dosya', 'renk'], true)) {
                            $hay .= ' ' . strip_tags($it[$ad]);
                        }
                    }
                    return str_contains(Str::lower($hay), $needle);
                }));
            }
            foreach ($filters as [$field, $op, $val]) {
                $items = array_values(array_filter($items, function ($it) use ($field, $op, $val) {
                    $v = $it[$field] ?? '';
                    if (is_array($v)) {
                        $match = isset($v['slug']) ? ($v['slug'] === $val || (string) $v['id'] === $val)
                            : in_array($val, $v, true);
                    } else {
                        $match = Str::lower((string) $v) === Str::lower($val);
                    }
                    return $op === '!=' ? !$match : $match;
                }));
            }
            if (!$order) {
                usort($items, function ($a, $b) use ($sort, $dir) {
                    $x = $a[$sort] ?? '';
                    $y = $b[$sort] ?? '';
                    $c = is_numeric($x) && is_numeric($y) ? $x <=> $y : strcoll((string) $x, (string) $y);
                    return $dir === 'ASC' ? $c : -$c;
                });
            }
            $total = count($items);
            $items = array_slice($items, ($page - 1) * $limit, $limit);
        }
        return [
            'ogeler' => $items,
            'toplam' => $total,
            'sayfa' => $page,
            'sayfa_sayisi' => (int) max(1, ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    private static function parseFilters(string $f): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(';', $f))) as $part) {
            if (preg_match('/^([a-z0-9_]+)\s*(!=|=)\s*(.*)$/i', $part, $m)) {
                $out[] = [$m[1], $m[2], trim($m[3])];
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------ kaydetme */

    /**
     * Yönetim formundan gelen değerleri doğrular ve normalleştirir.
     * @return array{0: array, 1: array} [değerler, hatalar]
     */
    public static function normalize(array $defs, array $input, array $files = [], string $prefix = ''): array
    {
        $values = [];
        $errors = [];
        foreach ($defs as $ad => $def) {
            $raw = $input[$ad] ?? null;
            $label = $def['etiket'];
            switch ($def['tur']) {
                case 'zengin':
                    $v = Sanitizer::clean((string) $raw);
                    break;
                case 'uzunmetin':
                case 'metin':
                case 'telefon':
                case 'harita':
                case 'video':
                    $v = trim(strip_tags((string) $raw));
                    if ($def['tur'] === 'telefon' && $v !== '') {
                        $v = Str::phone($v);
                    }
                    break;
                case 'eposta':
                    $v = trim((string) $raw);
                    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $errors[$prefix . $ad] = t('hata_eposta', $label);
                    }
                    break;
                case 'url':
                    $v = trim((string) $raw);
                    if ($v !== '' && !preg_match('#^(https?://|/|mailto:|tel:)#i', $v)) {
                        $v = 'https://' . $v;
                    }
                    break;
                case 'sayi':
                case 'fiyat':
                    $v = trim(str_replace(',', '.', (string) $raw));
                    if ($v !== '' && !is_numeric($v)) {
                        $errors[$prefix . $ad] = t('hata_sayi', $label);
                    }
                    break;
                case 'onay':
                    $v = !empty($raw) ? '1' : '';
                    break;
                case 'secim':
                    $v = (string) $raw;
                    if ($v !== '' && $def['secenekler'] && !in_array($v, self::optionValues($def['secenekler']), true)) {
                        $v = '';
                    }
                    break;
                case 'coklusecim':
                    $v = array_values(array_intersect((array) ($raw ?? []), self::optionValues($def['secenekler'])));
                    break;
                case 'tarih':
                case 'tarihsaat':
                    $v = trim((string) $raw);
                    if ($v !== '' && !strtotime($v)) {
                        $errors[$prefix . $ad] = t('hata_tarih', $label);
                    }
                    break;
                case 'tckn':
                    $v = preg_replace('/\D/', '', (string) $raw) ?? '';
                    if ($v !== '' && !Validate::tckn($v)) {
                        $errors[$prefix . $ad] = '“' . $label . '” geçerli bir T.C. kimlik numarası değil.';
                    }
                    break;
                case 'vkn':
                    $v = preg_replace('/\D/', '', (string) $raw) ?? '';
                    if ($v !== '' && !Validate::vkn($v)) {
                        $errors[$prefix . $ad] = '“' . $label . '” geçerli bir vergi kimlik numarası değil.';
                    }
                    break;
                case 'iban':
                    $v = strtoupper(preg_replace('/\s+/', '', (string) $raw) ?? '');
                    if ($v !== '' && !Validate::iban($v)) {
                        $errors[$prefix . $ad] = '“' . $label . '” geçerli bir IBAN değil.';
                    }
                    break;
                case 'il':
                    $v = in_array((string) $raw, Validate::ILLER, true) ? (string) $raw : '';
                    break;
                case 'markdown':
                    $v = (string) $raw;
                    break;
                case 'bloklar':
                    $v = [];
                    foreach ((array) ($raw ?? []) as $i => $rowIn) {
                        $tip = is_array($rowIn) ? (string) ($rowIn['_tip'] ?? '') : '';
                        if (!isset($def['bloklar'][$tip])) {
                            continue;
                        }
                        [$rv, $re] = self::normalize($def['bloklar'][$tip]['alt'] ?? [], $rowIn, [], "$ad.$i.");
                        $v[] = ['_tip' => $tip] + $rv;
                        $errors += $re;
                    }
                    break;
                case 'renk':
                    $v = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $raw) ? (string) $raw : '';
                    break;
                case 'iliski':
                    $v = (string) (int) $raw ?: '';
                    break;
                case 'resim':
                case 'dosya':
                    $v = trim((string) $raw);
                    if ($v !== '' && (str_contains($v, '..') || !str_starts_with($v, 'yuklemeler/'))) {
                        $v = '';
                    }
                    break;
                case 'tekrar':
                    $v = [];
                    foreach ((array) ($raw ?? []) as $i => $rowIn) {
                        if (!is_array($rowIn) || $i === '__sablon__') {
                            continue;
                        }
                        [$rv, $re] = self::normalize($def['alt'] ?? [], $rowIn, [], "$ad.$i.");
                        if (array_filter($rv, fn($x) => $x !== '' && $x !== [])) {
                            $v[] = $rv;
                        }
                        $errors += $re;
                    }
                    break;
                default:
                    $v = trim((string) $raw);
            }
            if ($def['zorunlu'] && ($v === '' || $v === [] || $v === null)) {
                $errors[$prefix . $ad] = t('hata_zorunlu', $label);
            }
            if ($def['en_fazla'] && is_string($v) && mb_strlen(strip_tags($v)) > $def['en_fazla']) {
                $errors[$prefix . $ad] = t('hata_uzun', $label, $def['en_fazla']);
            }
            $values[$ad] = $v;
        }
        return [$values, $errors];
    }

    /** "Kırmızı=kirmizi, Mavi" → değerler listesi */
    public static function optionValues(array $opts): array
    {
        return array_map(fn($o) => str_contains($o, '=') ? trim(explode('=', $o, 2)[1]) : $o, $opts);
    }

    public static function optionPairs(array $opts): array
    {
        $out = [];
        foreach ($opts as $o) {
            if (str_contains($o, '=')) {
                [$l, $v] = array_map('trim', explode('=', $o, 2));
                $out[$v] = $l;
            } else {
                $out[$o] = $o;
            }
        }
        return $out;
    }

    public static function saveRevision(array $row): void
    {
        $db = App::db();
        $db->insert('bz_surumler', [
            'icerik_id' => $row['id'], 'baslik' => $row['baslik'], 'veri' => $row['veri'], 'seo' => $row['seo'],
            'kullanici_id' => Auth::user()['id'] ?? null, 'tarih' => bz_now(),
        ]);
        // her içerik için son 25 sürüm tutulur
        $keep = $db->all('SELECT id FROM bz_surumler WHERE icerik_id = ? ORDER BY id DESC LIMIT 25', [$row['id']]);
        if (count($keep) === 25) {
            $db->q('DELETE FROM bz_surumler WHERE icerik_id = ? AND id < ?', [$row['id'], end($keep)['id']]);
        }
    }

    /**
     * Tek kayıt noktası: panel, API, MCP ve içe aktarıcı aynı yoldan kaydeder.
     * $input: ham alan değerleri; $meta: baslik, slug, durum, yayin_tarihi, sira, seo(array)
     * @return array{0:int, 1:array} [id, hatalar]
     */
    public static function saveEntry(string $name, array $input, array $meta, ?array $existing = null, ?string $lang = null, ?int $userId = null, ?int $group = null): array
    {
        $tpl = self::template($name);
        if (!$tpl) {
            return [0, ['sablon' => 'Şablon bulunamadı.']];
        }
        $lang = $existing['dil'] ?? ($lang && in_array($lang, App::languages(), true) ? $lang : App::defaultLang());
        $multi = $tpl['meta']['coklu'];
        [$values, $errors] = self::normalize($tpl['alanlar'], $input);
        $title = trim((string) ($meta['baslik'] ?? ($existing['baslik'] ?? '')));
        if ($multi && $title === '') {
            $errors['baslik'] = 'Başlık zorunludur.';
        }
        if ($errors) {
            return [0, $errors];
        }
        $id = (int) ($existing['id'] ?? 0);
        $slug = '';
        if ($multi) {
            $slug = self::uniqueSlug($name, Str::slug((string) ($meta['slug'] ?? '')) ?: ($existing['slug'] ?? '') ?: Str::slug($title), $id, $lang);
        }
        $date = (string) ($meta['yayin_tarihi'] ?? '');
        $seo = $meta['seo'] ?? (json_decode((string) ($existing['seo'] ?? '{}'), true) ?: []);
        unset($seo['ceviri_bekliyor']); // kaydedilen çeviri artık "bekliyor" değil
        $data = [
            'baslik' => mb_substr($multi ? $title : ($title ?: $tpl['meta']['baslik']), 0, 255),
            'slug' => $slug,
            'durum' => $multi ? (($meta['durum'] ?? 'taslak') === 'yayinda' ? 'yayinda' : 'taslak') : 'yayinda',
            'yayin_tarihi' => $date !== '' && strtotime($date) ? date('Y-m-d H:i:s', strtotime($date)) : ($existing['yayin_tarihi'] ?? bz_now()),
            'sira' => (int) ($meta['sira'] ?? ($existing['sira'] ?? 0)),
            'veri' => json_encode($values, JSON_UNESCAPED_UNICODE),
            'seo' => json_encode($seo, JSON_UNESCAPED_UNICODE),
            'guncelleme' => bz_now(),
        ];
        $db = App::db();
        if ($existing) {
            self::saveRevision($existing);
            $db->update('bz_icerik', $data, 'id = ?', [$id]);
            // Adres değiştiyse eski adresten 301 yönlendirme (SEO kaybını önler)
            if ($multi && $existing['slug'] !== '' && $existing['slug'] !== $slug && $existing['durum'] === 'yayinda') {
                Redirects::add(ltrim(self::url($existing), '/'), self::url(['slug' => $slug] + $existing));
            }
            bz_log('icerik_guncelle', "$name #$id {$data['baslik']}");
            $event = 'icerik.guncellendi';
        } else {
            $id = $db->insert('bz_icerik', $data + ['sablon' => $name, 'dil' => $lang, 'grup' => (int) $group, 'yazar_id' => $userId, 'olusturma' => bz_now()]);
            if (!$group) {
                $db->update('bz_icerik', ['grup' => $id], 'id = ?', [$id]);
            }
            bz_log('icerik_ekle', "$name #$id {$data['baslik']}");
            $event = 'icerik.eklendi';
        }
        Cache::clear();
        Webhook::fire($event, ['id' => $id, 'sablon' => $name, 'dil' => $lang, 'baslik' => $data['baslik'], 'durum' => $data['durum'], 'url' => App::siteUrl() . self::url(self::find($id))]);
        return [$id, []];
    }

    public static function deleteEntry(array $row): void
    {
        App::db()->q('DELETE FROM bz_surumler WHERE icerik_id = ?', [$row['id']]);
        App::db()->q('DELETE FROM bz_icerik WHERE id = ?', [$row['id']]);
        Cache::clear();
        bz_log('icerik_sil', "{$row['sablon']} #{$row['id']} {$row['baslik']}");
        Webhook::fire('icerik.silindi', ['id' => (int) $row['id'], 'sablon' => $row['sablon'], 'baslik' => $row['baslik']]);
    }

    /** API/MCP çıktısı: api="hayir" alanlar hariç */
    public static function publicView(array $row): array
    {
        $it = self::hydrate($row);
        foreach (self::template($row['sablon'])['alanlar'] ?? [] as $ad => $def) {
            if (empty($def['api'])) {
                unset($it[$ad]);
            }
        }
        array_walk_recursive($it, function (&$v) {
            if (is_string($v) && str_starts_with($v, App::$basePath . 'yuklemeler/')) {
                $v = App::siteUrl() . $v;
            }
        });
        return $it;
    }
}
