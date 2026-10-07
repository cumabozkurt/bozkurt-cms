<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Yönetim paneli denetleyicisi: /yonetim/?s=bolum
 */
final class Admin
{
    /** Bölüm → gereken yetki ('*' = yalnızca yönetici, '' = giriş yapmış herkes) */
    private const SECTIONS = [
        'panel' => 'icerik', 'icerik' => 'icerik', 'duzenle' => 'icerik', 'sil' => 'icerik', 'surum' => 'icerik', 'kopyala' => 'icerik',
        'satir_ici' => 'icerik', 'yz' => 'icerik', 'takvim' => 'icerik', 'medya' => 'medya', 'formlar' => 'formlar', 'genel' => 'genel',
        'siparisler' => 'formlar', 'seo' => 'genel', 'yonlendirmeler' => 'genel', 'profil' => '',
        'kullanicilar' => '*', 'ayarlar' => '*', 'yedek' => '*', 'gunluk' => '*', 'sistem' => '*', 'api' => '*', 'araclar' => '*',
    ];

    public static function run(): void
    {
        bz_security_headers(true);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        header('Referrer-Policy: same-origin');
        if (!App::installed()) {
            Installer::run();
            return;
        }
        App::ensureSchema();
        bz_session();
        $s = preg_replace('/[^a-z_]/', '', (string) ($_GET['s'] ?? 'panel')) ?: 'panel';
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

        if ($post && !bz_csrf_check()) {
            if (self::wantsJson()) {
                bz_json(['ok' => false, 'hata' => 'Oturum süresi doldu, sayfayı yenileyin.'], 419);
            }
            bz_flash('Güvenlik belirteci geçersiz. Lütfen tekrar deneyin.', 'hata');
            bz_redirect(App::adminUrl($s === 'giris' ? 'giris' : 'panel'));
        }
        // Panel dili: kullanıcının tercihi → çıktı sözlükle çevrilir
        ob_start(fn(string $html) => Lang::translateAdmin($html));

        if (in_array($s, ['giris', 'dogrulama', 'sifremi_unuttum', 'sifre_yenile'], true)) {
            self::$s($post);
            return;
        }
        if (!Auth::check()) {
            if (self::wantsJson()) {
                bz_json(['ok' => false, 'hata' => 'Oturum kapandı. Lütfen yeniden giriş yapın.'], 401);
            }
            bz_redirect(App::adminUrl('giris'));
        }
        if ($s === 'cikis') {
            if ($post) {
                Auth::logout();
            }
            bz_redirect(App::adminUrl('giris'));
        }
        if (!isset(self::SECTIONS[$s])) {
            $s = 'panel';
        }
        $perm = self::SECTIONS[$s];
        if ($perm !== '' && !Auth::can($perm)) {
            http_response_code(403);
            self::view('hata', ['baslik' => 'Yetkiniz yok', 'mesaj' => 'Bu bölüm için yetkiniz bulunmuyor.']);
            return;
        }
        Forms::purgeOld();
        self::$s($post);
    }

    private static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_GET['json']);
    }

    public static function view(string $name, array $vars = [], bool $layout = true): void
    {
        require_once BZ_CORE . '/gorunumler/_yardim.php';
        extract($vars, EXTR_SKIP);
        $user = Auth::user();
        $templates = App::installed() ? Content::templates() : [];
        $section = $_GET['s'] ?? 'panel';
        ob_start();
        require BZ_CORE . '/gorunumler/' . $name . '.php';
        $content = (string) ob_get_clean();
        if ($layout) {
            $flash = bz_flash();
            require BZ_CORE . '/gorunumler/_duzen.php';
        } else {
            echo $content;
        }
    }

    /** Panelde düzenlenen dil (çok dilli sitelerde ?dil=en) */
    private static function editLang(): string
    {
        $l = (string) ($_GET['dil'] ?? App::defaultLang());
        return in_array($l, App::languages(), true) ? $l : App::defaultLang();
    }

    /* ------------------------------------------------------------ giriş ve şifre */

    private static function giris(bool $post): void
    {
        if (Auth::check()) {
            bz_redirect(App::adminUrl());
        }
        $error = null;
        if ($post) {
            $r = Auth::attempt((string) ($_POST['eposta'] ?? ''), (string) ($_POST['sifre'] ?? ''));
            if ($r['ok']) {
                bz_redirect(App::adminUrl());
            }
            if (!empty($r['totp'])) {
                bz_redirect(App::adminUrl('dogrulama'));
            }
            $error = $r['hata'] ?? t('giris_hatali');
        }
        self::view('giris', ['hata' => $error, 'eposta' => mb_substr((string) ($_POST['eposta'] ?? ''), 0, 190)], false);
    }

    private static function dogrulama(bool $post): void
    {
        if (empty($_SESSION['bz_totp_bekleyen'])) {
            bz_redirect(App::adminUrl('giris'));
        }
        $error = null;
        if ($post) {
            if (Auth::verifyTotp((string) ($_POST['kod'] ?? ''))) {
                bz_redirect(App::adminUrl());
            }
            $error = Auth::tooManyAttempts() ? t('giris_kilit', 15) : 'Kod hatalı veya süresi geçmiş.';
        }
        self::view('dogrulama', ['hata' => $error], false);
    }

    private static function sifremi_unuttum(bool $post): void
    {
        $sent = false;
        if ($post) {
            Auth::requestReset((string) ($_POST['eposta'] ?? ''));
            $sent = true;
        }
        self::view('sifremi_unuttum', ['gonderildi' => $sent], false);
    }

    private static function sifre_yenile(bool $post): void
    {
        $t = (string) ($_GET['t'] ?? '');
        $valid = Auth::resetUser($t) !== null;
        $error = null;
        if ($post && $valid) {
            $p1 = (string) ($_POST['sifre'] ?? '');
            if (strlen($p1) < 10 || $p1 !== ($_POST['sifre2'] ?? '')) {
                $error = 'Şifre en az 10 karakter olmalı ve iki alan eşleşmeli.';
            } elseif (Auth::completeReset($t, $p1)) {
                bz_flash('Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
                bz_redirect(App::adminUrl('giris'));
            }
        }
        self::view('sifre_yenile', ['gecerli' => $valid, 'hata' => $error], false);
    }

    /* ------------------------------------------------------------ panel */

    private static function panel(bool $post): void
    {
        $db = App::db();
        $counts = [];
        foreach (Content::templates() as $n => $t) {
            if ($t['meta']['coklu']) {
                $counts[$n] = (int) $db->val("SELECT COUNT(*) FROM bz_icerik WHERE sablon = ? AND slug != '' AND dil = ?", [$n, App::defaultLang()]);
            }
        }
        $checks = [
            ['Site adını ve açıklamasını girin', App::setting('site_aciklama') && !str_ends_with((string) App::setting('site_aciklama'), 'resmi web sitesi'), App::adminUrl('ayarlar')],
            ['İki adımlı doğrulamayı açın', !empty(Auth::user()['totp_gizli']), App::adminUrl('profil')],
            ['SMTP e-posta ayarlarını yapın', (bool) App::setting('smtp_sunucu'), App::adminUrl('ayarlar') . '#eposta'],
            ['KVKK aydınlatma metnini düzenleyin', (bool) $db->val("SELECT COUNT(*) FROM bz_surumler s JOIN bz_icerik i ON i.id = s.icerik_id WHERE i.sablon = 'kvkk'"), App::adminUrl('duzenle', ['sablon' => 'kvkk'])],
            ['Google ve Yandex doğrulamasını ekleyin', App::setting('google_dogrulama') || App::setting('yandex_dogrulama'), App::adminUrl('ayarlar') . '#seo'],
            ['İlk tam yedeği indirin', (bool) $db->val("SELECT COUNT(*) FROM bz_gunluk WHERE islem = 'yedek_indir'"), App::adminUrl('yedek')],
        ];
        self::view('panel', [
            'sayilar' => $counts,
            'son_icerik' => $db->all("SELECT id, sablon, baslik, durum, guncelleme, dil FROM bz_icerik WHERE slug != '' ORDER BY guncelleme DESC LIMIT 6"),
            'son_formlar' => Auth::can('formlar') ? $db->all('SELECT * FROM bz_formlar ORDER BY id DESC LIMIT 5') : [],
            'okunmamis' => (int) $db->val('SELECT COUNT(*) FROM bz_formlar WHERE okundu = 0'),
            'medya_sayisi' => (int) $db->val('SELECT COUNT(*) FROM bz_medya'),
            'taslak_sayisi' => (int) $db->val("SELECT COUNT(*) FROM bz_icerik WHERE durum = 'taslak'"),
            'zamanlanan' => $db->all("SELECT id, baslik, yayin_tarihi FROM bz_icerik WHERE durum = 'yayinda' AND yayin_tarihi > ? ORDER BY yayin_tarihi LIMIT 5", [bz_now()]),
            'kontroller' => Auth::can('*') ? $checks : [],
            'hata_404' => Auth::can('genel') ? (int) $db->val('SELECT COUNT(*) FROM bz_404 WHERE sayac >= 3') : 0,
        ]);
    }

    /* ------------------------------------------------------------ içerik */

    private static function icerik(bool $post): void
    {
        $name = (string) ($_GET['sablon'] ?? '');
        $tpl = Content::template($name);
        $lang = self::editLang();
        if (!$tpl) {
            self::view('sablonlar');
            return;
        }
        if (!$tpl['meta']['coklu']) {
            bz_redirect(App::adminUrl('duzenle', ['sablon' => $name, 'dil' => $lang]));
        }
        if ($post && Auth::can('icerik_tum')) {
            $ids = array_slice(array_map('intval', (array) ($_POST['secili'] ?? [])), 0, 500);
            $action = $_POST['toplu'] ?? '';
            if ($ids && in_array($action, ['yayinla', 'taslak', 'sil'], true)) {
                if ($action === 'sil') {
                    foreach ($ids as $i) {
                        $row = Content::find($i);
                        if ($row && $row['sablon'] === $name && $row['slug'] !== '') {
                            Content::deleteEntry($row);
                        }
                    }
                } elseif (Auth::can('yayinla')) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    App::db()->q("UPDATE bz_icerik SET durum = ? WHERE id IN ($ph) AND sablon = ?", [$action === 'yayinla' ? 'yayinda' : 'taslak', ...$ids, $name]);
                    Cache::clear();
                }
                bz_log('toplu_' . $action, "$name: " . count($ids) . ' içerik');
                bz_flash(count($ids) . ' içerik güncellendi.');
            }
            bz_redirect(App::adminUrl('icerik', ['sablon' => $name, 'dil' => $lang]));
        }
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $durum = (string) ($_GET['durum'] ?? '');
        $page = max(1, (int) ($_GET['sayfa'] ?? 1));
        $where = "sablon = ? AND slug != '' AND dil = ?";
        $p = [$name, $lang];
        if ($q !== '') {
            $where .= ' AND baslik LIKE ?';
            $p[] = '%' . addcslashes($q, '%_') . '%';
        }
        if (in_array($durum, ['yayinda', 'taslak'], true)) {
            $where .= ' AND durum = ?';
            $p[] = $durum;
        }
        if (!Auth::can('icerik_tum')) {
            $where .= ' AND yazar_id = ?';
            $p[] = Auth::user()['id'];
        }
        $per = 25;
        $total = (int) App::db()->val("SELECT COUNT(*) FROM bz_icerik WHERE $where", $p);
        $rows = App::db()->all("SELECT id, baslik, slug, durum, yayin_tarihi, guncelleme, sira, sablon, dil, grup FROM bz_icerik WHERE $where ORDER BY yayin_tarihi DESC, id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        // çeviri durumu
        $tr = [];
        if (App::isMultilingual() && $rows) {
            $groups = array_map(fn($r) => (int) $r['grup'] ?: (int) $r['id'], $rows);
            $ph = implode(',', array_fill(0, count($groups), '?'));
            foreach (App::db()->all("SELECT grup, dil FROM bz_icerik WHERE grup IN ($ph)", $groups) as $r) {
                $tr[(int) $r['grup']][] = $r['dil'];
            }
        }
        self::view('icerik', ['ad' => $name, 'sablon' => $tpl, 'satirlar' => $rows, 'toplam' => $total, 'sayfa' => $page, 'sayfa_sayisi' => (int) ceil($total / $per),
            'q' => $q, 'durum' => $durum, 'dil' => $lang, 'ceviriler' => $tr]);
    }

    private static function canEdit(?array $row): bool
    {
        return !$row || Auth::can('icerik_tum') || (int) $row['yazar_id'] === (int) Auth::user()['id'];
    }

    private static function duzenle(bool $post): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $row = $id ? Content::find($id) : null;
        $name = $row['sablon'] ?? (string) ($_GET['sablon'] ?? '');
        $tpl = Content::template($name);
        if (!$tpl) {
            bz_flash('Şablon bulunamadı.', 'hata');
            bz_redirect(App::adminUrl('icerik'));
        }
        $lang = $row['dil'] ?? self::editLang();
        if (!$tpl['meta']['coklu']) {
            $row = Content::single($name, $lang);
            $id = (int) $row['id'];
        }
        if (!self::canEdit($row)) {
            bz_flash('Bu içeriği düzenleme yetkiniz yok.', 'hata');
            bz_redirect(App::adminUrl('icerik', ['sablon' => $name]));
        }
        // Çeviri oluşturma: ?kaynak=ID&dil=en
        $source = (!$row && isset($_GET['kaynak'])) ? Content::find((int) $_GET['kaynak']) : null;
        if ($source && ($source['sablon'] !== $name || $source['dil'] === $lang)) {
            $source = null;
        }
        if ($source && ($existingTr = Content::translations($source)[$lang] ?? null)) {
            bz_redirect(App::adminUrl('duzenle', ['id' => $existingTr['id']]));
        }
        $errors = [];
        $base = $row ?? $source;
        $values = $base ? (json_decode((string) $base['veri'], true) ?: []) : [];
        $seo = $base ? (json_decode((string) $base['seo'], true) ?: []) : [];
        $meta = [
            'baslik' => $base['baslik'] ?? '', 'slug' => $row['slug'] ?? '', 'durum' => $row['durum'] ?? 'taslak',
            'yayin_tarihi' => $base['yayin_tarihi'] ?? bz_now(), 'sira' => $base['sira'] ?? 0,
        ];
        if (!$base) {
            foreach ($tpl['alanlar'] as $ad => $def) {
                $values[$ad] = in_array($def['tur'], ['tekrar', 'bloklar'], true) ? [] : $def['varsayilan'];
            }
        }

        if ($post) {
            $seoIn = [
                'baslik' => mb_substr(trim((string) ($_POST['seo']['baslik'] ?? '')), 0, 120),
                'aciklama' => mb_substr(trim((string) ($_POST['seo']['aciklama'] ?? '')), 0, 300),
                'anahtar' => mb_substr(trim((string) ($_POST['seo']['anahtar'] ?? '')), 0, 80),
                'resim' => preg_match('#^yuklemeler/[^.][^\0]*$#', (string) ($_POST['seo']['resim'] ?? '')) && !str_contains((string) $_POST['seo']['resim'], '..') ? (string) $_POST['seo']['resim'] : '',
                'noindex' => !empty($_POST['seo']['noindex']),
            ];
            $wanted = ($_POST['eylem'] ?? '') === 'taslak' ? 'taslak' : 'yayinda';
            if ($wanted === 'yayinda' && !Auth::can('yayinla')) {
                $wanted = 'taslak';
            }
            [$newId, $errors] = Content::saveEntry($name, (array) ($_POST['alan'] ?? []), [
                'baslik' => (string) ($_POST['baslik'] ?? ($row['baslik'] ?? '')), 'slug' => (string) ($_POST['slug'] ?? ''),
                'durum' => $wanted, 'yayin_tarihi' => (string) ($_POST['yayin_tarihi'] ?? ''), 'sira' => (int) ($_POST['sira'] ?? 0), 'seo' => $seoIn,
            ], $row, $lang, (int) Auth::user()['id'], $source ? ((int) $source['grup'] ?: (int) $source['id']) : null);
            if (!$errors) {
                if (self::wantsJson()) {
                    bz_json(['ok' => true, 'id' => $newId, 'url' => Content::url(Content::find($newId))]);
                }
                bz_flash($tpl['meta']['coklu'] && $wanted === 'taslak' ? 'Taslak kaydedildi.' : 'Kaydedildi ve yayınlandı.');
                bz_redirect(App::adminUrl('duzenle', ['id' => $newId]));
            }
            if (self::wantsJson()) {
                bz_json(['ok' => false, 'hatalar' => $errors], 422);
            }
            [$values] = Content::normalize($tpl['alanlar'], (array) ($_POST['alan'] ?? []));
            $seo = $seoIn;
            $meta['baslik'] = (string) ($_POST['baslik'] ?? '');
            $meta['slug'] = (string) ($_POST['slug'] ?? '');
        }
        $revisions = $id ? App::db()->all('SELECT s.id, s.tarih, k.ad FROM bz_surumler s LEFT JOIN bz_kullanicilar k ON k.id = s.kullanici_id WHERE s.icerik_id = ? ORDER BY s.id DESC LIMIT 15', [$id]) : [];
        self::view('duzenle', [
            'ad' => $name, 'sablon' => $tpl, 'id' => $id, 'kayit' => $row, 'degerler' => $values, 'seo' => $seo, 'meta' => $meta,
            'hatalar' => $errors, 'surumler' => $revisions, 'dil' => $lang, 'kaynak' => $source,
            'ceviriler' => $row ? Content::translations($row) : ($source ? Content::translations($source) : []),
        ]);
    }

    private static function sil(bool $post): void
    {
        $row = $post ? Content::find((int) ($_POST['id'] ?? 0)) : null;
        if ($row && $row['slug'] !== '' && self::canEdit($row)) {
            Content::deleteEntry($row);
            bz_flash('“' . $row['baslik'] . '” silindi.');
            bz_redirect(App::adminUrl('icerik', ['sablon' => $row['sablon'], 'dil' => $row['dil']]));
        }
        bz_redirect(App::adminUrl('icerik'));
    }

    private static function kopyala(bool $post): void
    {
        $row = $post ? Content::find((int) ($_POST['id'] ?? 0)) : null;
        if ($row && $row['slug'] !== '' && self::canEdit($row)) {
            $id = App::db()->insert('bz_icerik', [
                'sablon' => $row['sablon'], 'baslik' => mb_substr($row['baslik'] . ' (kopya)', 0, 255), 'slug' => Content::uniqueSlug($row['sablon'], $row['slug'] . '-kopya', 0, $row['dil']),
                'durum' => 'taslak', 'yayin_tarihi' => bz_now(), 'veri' => $row['veri'], 'seo' => $row['seo'], 'sira' => $row['sira'], 'dil' => $row['dil'], 'grup' => 0,
                'yazar_id' => Auth::user()['id'], 'olusturma' => bz_now(), 'guncelleme' => bz_now(),
            ]);
            App::db()->update('bz_icerik', ['grup' => $id], 'id = ?', [$id]);
            bz_log('icerik_kopyala', "#{$row['id']} → #$id");
            bz_flash('Kopya taslak olarak oluşturuldu.');
            bz_redirect(App::adminUrl('duzenle', ['id' => $id]));
        }
        bz_redirect(App::adminUrl('icerik'));
    }

    private static function surum(bool $post): void
    {
        $rev = $post ? App::db()->one('SELECT * FROM bz_surumler WHERE id = ?', [(int) ($_POST['id'] ?? 0)]) : null;
        $row = $rev ? Content::find((int) $rev['icerik_id']) : null;
        if ($rev && $row && self::canEdit($row)) {
            Content::saveRevision($row);
            App::db()->update('bz_icerik', ['baslik' => $rev['baslik'], 'veri' => $rev['veri'], 'seo' => $rev['seo'], 'guncelleme' => bz_now()], 'id = ?', [$row['id']]);
            Cache::clear();
            bz_log('surum_geri_yukle', "#{$row['id']} ← sürüm {$rev['id']}");
            bz_flash('Sürüm geri yüklendi (' . Str::date($rev['tarih'], 'd F Y H:i') . ').');
            bz_redirect(App::adminUrl('duzenle', ['id' => $row['id']]));
        }
        bz_redirect(App::adminUrl());
    }

    /** Ön yüzde satır içi düzenleme kaydı (JSON) */
    private static function satir_ici(bool $post): void
    {
        $row = $post ? Content::find((int) ($_POST['id'] ?? 0)) : null;
        $field = (string) ($_POST['alan'] ?? '');
        $tpl = $row ? Content::template($row['sablon']) : null;
        $def = $tpl['alanlar'][$field] ?? null;
        if (!$row || !$def || !in_array($def['tur'], ['metin', 'uzunmetin', 'zengin'], true) || !self::canEdit($row)) {
            bz_json(['ok' => false, 'hata' => 'Bu alan sayfada düzenlenemez.'], 400);
        }
        $values = json_decode((string) $row['veri'], true) ?: [];
        $values[$field] = mb_substr((string) ($_POST['deger'] ?? ''), 0, 200000);
        // Yayınlama yetkisi olmayan (yazar) değişikliği taslağa düşer — panel düzenleyicisiyle aynı kural
        $durum = Auth::can('yayinla') ? $row['durum'] : 'taslak';
        [, $errors] = Content::saveEntry($row['sablon'], $values, ['baslik' => $row['baslik'], 'durum' => $durum, 'slug' => $row['slug']], $row);
        $errors ? bz_json(['ok' => false, 'hata' => implode(' ', $errors)], 422) : bz_json(['ok' => true]);
    }

    /** Yapay zekâ görevleri (JSON) */
    private static function yz(bool $post): void
    {
        if (!$post) {
            bz_json(['ok' => false, 'hata' => 'POST gerekli.'], 405);
        }
        @set_time_limit(90);
        $task = (string) ($_POST['gorev'] ?? '');
        $text = (string) ($_POST['metin'] ?? '');
        $res = Ai::task($task, $text, ['dil' => (string) ($_POST['dil'] ?? App::defaultLang()), 'komut' => (string) ($_POST['komut'] ?? '')]);
        if ($res['ok']) {
            bz_log('yz_' . preg_replace('/[^a-z]/', '', $task), mb_substr(strip_tags($text), 0, 60));
        }
        bz_json($res, $res['ok'] ? 200 : 422);
    }

    private static function takvim(bool $post): void
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['ay'] ?? '')) ? (string) $_GET['ay'] : date('Y-m');
        $from = $month . '-01 00:00:00';
        $to = date('Y-m-d H:i:s', strtotime($from . ' +1 month'));
        $rows = App::db()->all("SELECT id, sablon, baslik, durum, yayin_tarihi, dil FROM bz_icerik WHERE slug != '' AND yayin_tarihi >= ? AND yayin_tarihi < ? ORDER BY yayin_tarihi", [$from, $to]);
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) substr($r['yayin_tarihi'], 8, 2)][] = $r;
        }
        self::view('takvim', ['ay' => $month, 'gunler' => $byDay]);
    }

    /* ------------------------------------------------------------ medya */

    private static function medya(bool $post): void
    {
        $db = App::db();
        if ($post) {
            $a = $_POST['eylem'] ?? 'yukle';
            if ($a === 'yukle') {
                $results = [];
                $files = $_FILES['dosya'] ?? null;
                if ($files && is_array($files['name'])) {
                    foreach (array_slice(array_keys($files['name']), 0, 50) as $i) {
                        $results[] = Media::upload(['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]]);
                    }
                } elseif ($files) {
                    $results[] = Media::upload($files);
                }
                bz_log('medya_yukle', count($results) . ' dosya');
                if (self::wantsJson()) {
                    bz_json(['ok' => true, 'sonuclar' => $results]);
                }
                foreach ($results as $r) {
                    bz_flash($r['ok'] ? 'Yüklendi: ' . basename($r['dosya']) : $r['hata'], $r['ok'] ? 'basari' : 'hata');
                }
            } elseif ($a === 'sil') {
                if (Auth::can('icerik_tum')) {
                    Media::delete((int) ($_POST['id'] ?? 0));
                    bz_log('medya_sil', '#' . (int) ($_POST['id'] ?? 0));
                    bz_flash('Dosya silindi.');
                } else {
                    bz_flash('Dosya silme yetkiniz yok.', 'hata');
                }
            } elseif ($a === 'alt') {
                $db->update('bz_medya', ['alt' => mb_substr(trim(strip_tags((string) ($_POST['alt'] ?? ''))), 0, 255)], 'id = ?', [(int) ($_POST['id'] ?? 0)]);
                if (self::wantsJson()) {
                    bz_json(['ok' => true]);
                }
            }
            bz_redirect(App::adminUrl('medya'));
        }
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $type = (string) ($_GET['tur'] ?? '');
        $where = '1=1';
        $p = [];
        if ($q !== '') {
            $where .= ' AND (orijinal LIKE ? OR alt LIKE ?)';
            $p[] = '%' . addcslashes($q, '%_') . '%';
            $p[] = '%' . addcslashes($q, '%_') . '%';
        }
        if ($type === 'resim') {
            $where .= " AND mime LIKE 'image/%'";
        } elseif ($type === 'belge') {
            $where .= " AND mime NOT LIKE 'image/%'";
        } elseif ($type === 'altsiz') {
            $where .= " AND mime LIKE 'image/%' AND (alt IS NULL OR alt = '')";
        }
        $items = $db->all("SELECT * FROM bz_medya WHERE $where ORDER BY id DESC LIMIT 200", $p);
        if (self::wantsJson()) {
            bz_json(['ogeler' => array_map(fn($m) => ['id' => (int) $m['id'], 'dosya' => $m['dosya'], 'orijinal' => $m['orijinal'], 'mime' => $m['mime'], 'alt' => $m['alt'], 'url' => bz_upload_url($m['dosya'])], $items)]);
        }
        self::view('medya', ['ogeler' => $items, 'q' => $q, 'tur' => $type]);
    }

    /* ------------------------------------------------------------ formlar ve siparişler */

    private static function formlar(bool $post): void
    {
        $db = App::db();
        if ($post) {
            $ids = array_slice(array_map('intval', (array) ($_POST['secili'] ?? [$_POST['id'] ?? 0])), 0, 1000);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            if (($_POST['eylem'] ?? '') === 'sil') {
                $db->q("DELETE FROM bz_formlar WHERE id IN ($ph)", $ids);
                bz_log('form_sil', count($ids) . ' kayıt');
                bz_flash(count($ids) . ' kayıt silindi.');
            } elseif (($_POST['eylem'] ?? '') === 'okundu') {
                $db->q("UPDATE bz_formlar SET okundu = 1 WHERE id IN ($ph)", $ids);
            }
            bz_redirect(App::adminUrl('formlar', array_filter(['form' => $_GET['form'] ?? null])));
        }
        $form = preg_replace('/[^a-z0-9_\-]/', '', (string) ($_GET['form'] ?? '')) ?? '';
        $names = $db->all('SELECT form, COUNT(*) AS adet, SUM(CASE WHEN okundu = 0 THEN 1 ELSE 0 END) AS yeni FROM bz_formlar GROUP BY form ORDER BY form');
        $where = $form !== '' ? 'WHERE form = ?' : '';
        $p = $form !== '' ? [$form] : [];
        if (isset($_GET['csv'])) {
            $rows = $db->all("SELECT * FROM bz_formlar $where ORDER BY id DESC", $p);
            $cols = [];
            foreach ($rows as $r) {
                $cols += array_flip(array_keys(json_decode((string) $r['veri'], true) ?: []));
            }
            $cols = array_keys($cols);
            bz_log('form_csv', $form ?: 'tümü');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="form-' . ($form ?: 'tum') . '-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Form', 'Tarih', 'KVKK Onayı', ...$cols], ';', '"', '\\');
            foreach ($rows as $r) {
                $d = json_decode((string) $r['veri'], true) ?: [];
                fputcsv($out, array_map('bz_csv_cell', [$r['id'], $r['form'], $r['tarih'], $r['kvkk_onay'] ? 'Evet' : 'Hayır', ...array_map(fn($c) => $d[$c] ?? '', $cols)]), ';', '"', '\\');
            }
            exit;
        }
        $view = isset($_GET['id']) ? $db->one('SELECT * FROM bz_formlar WHERE id = ?', [(int) $_GET['id']]) : null;
        if ($view && !$view['okundu']) {
            $db->update('bz_formlar', ['okundu' => 1], 'id = ?', [$view['id']]);
        }
        self::view('formlar', ['formlar' => $names, 'form' => $form, 'kayitlar' => $db->all("SELECT * FROM bz_formlar $where ORDER BY id DESC LIMIT 200", $p), 'goster' => $view]);
    }

    private static function siparisler(bool $post): void
    {
        $db = App::db();
        if (isset($_GET['csv'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="siparisler-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sipariş No', 'Tarih', 'Ürün', 'Tutar', 'Para', 'Durum', 'Sağlayıcı', 'Ad Soyad', 'E-posta', 'Telefon', 'Adres'], ';', '"', '\\');
            foreach ($db->all('SELECT * FROM bz_siparisler ORDER BY id DESC') as $r) {
                $m = json_decode((string) $r['musteri'], true) ?: [];
                fputcsv($out, array_map('bz_csv_cell', [$r['siparis_no'], $r['tarih'], $r['urun'], str_replace('.', ',', $r['tutar']), $r['para'], $r['durum'], $r['saglayici'],
                    $m['ad_soyad'] ?? '', $m['eposta'] ?? '', $m['telefon'] ?? '', $m['adres'] ?? '']), ';', '"', '\\');
            }
            exit;
        }
        self::view('siparisler', ['siparisler' => $db->all('SELECT * FROM bz_siparisler ORDER BY id DESC LIMIT 300'),
            'toplam' => (float) $db->val("SELECT SUM(CAST(tutar AS DECIMAL(12,2))) FROM bz_siparisler WHERE durum = 'odendi'")]);
    }

    /* ------------------------------------------------------------ genel alanlar */

    private static function genel(bool $post): void
    {
        $lang = self::editLang();
        $defs = Content::globalDefs();
        $values = [];
        foreach ($defs as $k => $d) {
            $values[$k] = Content::globalValue($k, $lang) ?? $d['varsayilan'];
        }
        $errors = [];
        if ($post) {
            [$values, $errors] = Content::normalize($defs, (array) ($_POST['alan'] ?? []));
            if (!$errors) {
                foreach ($values as $k => $v) {
                    App::db()->upsert('bz_genel', ['anahtar' => Content::globalKey($k, $lang), 'deger' => json_encode($v, JSON_UNESCAPED_UNICODE)], 'anahtar');
                }
                Content::clearGlobalsCache();
                Cache::clear();
                bz_log('genel_guncelle', $lang);
                bz_flash('Genel alanlar kaydedildi.');
                bz_redirect(App::adminUrl('genel', ['dil' => $lang]));
            }
        }
        self::view('genel', ['tanimlar' => $defs, 'degerler' => $values, 'hatalar' => $errors, 'dil' => $lang]);
    }

    /* ------------------------------------------------------------ SEO araçları ve yönlendirmeler */

    private static function seo(bool $post): void
    {
        $db = App::db();
        $issues = [];
        $links = [];
        $titles = [];
        foreach ($db->all("SELECT id, sablon, baslik, slug, seo, veri, dil FROM bz_icerik WHERE durum = 'yayinda' LIMIT 3000") as $r) {
            $seo = json_decode((string) $r['seo'], true) ?: [];
            $veri = json_decode((string) $r['veri'], true) ?: [];
            $html = implode(' ', array_filter($veri, 'is_string'));
            $edit = App::adminUrl('duzenle', ['id' => $r['id']]);
            $title = $seo['baslik'] ?? '' ?: $r['baslik'];
            $titles[$r['dil'] . '|' . Str::lower($title)][] = $r;
            if ($r['slug'] !== '' && empty($seo['aciklama']) && mb_strlen(strip_tags($html)) < 120) {
                $issues[] = ['Meta açıklama yok ve içerik çok kısa', $r, $edit];
            }
            if (mb_strlen($title) > 65) {
                $issues[] = ['Başlık 65 karakterden uzun (arama sonucunda kesilir)', $r, $edit];
            }
            if (preg_match_all('/<img\b(?![^>]*\balt="[^"]+")[^>]*>/i', $html) > 0) {
                $issues[] = ['Alt metni olmayan görsel var', $r, $edit];
            }
            if (preg_match_all('#href="(/[^"\#?]*)#', $html, $m)) {
                foreach ($m[1] as $l) {
                    $links[$l][] = $r;
                }
            }
        }
        foreach ($titles as $rows) {
            if (count($rows) > 1) {
                foreach ($rows as $r) {
                    $issues[] = ['Aynı başlığa sahip başka sayfa var (yinelenen başlık)', $r, App::adminUrl('duzenle', ['id' => $r['id']])];
                }
            }
        }
        // kırık iç bağlantılar
        $broken = [];
        $oldLang = App::$lang;
        foreach (array_slice($links, 0, 500, true) as $l => $rows) {
            $path = trim(substr($l, strlen(App::$basePath) - 1), '/');
            if (preg_match('#^(yuklemeler|tema)/#', $path)) {
                if (!is_file(BZ_ROOT . '/' . rawurldecode($path))) {
                    $broken[] = [$l, $rows];
                }
                continue;
            }
            $seg = explode('/', $path);
            if (in_array($seg[0], App::languages(), true) && $seg[0] !== App::defaultLang()) {
                App::$lang = array_shift($seg);
            }
            $tpl = Content::template($seg[0] ?: 'ana-sayfa');
            $ok = $tpl && (!isset($seg[1]) || ($tpl['meta']['coklu'] && Content::findBySlug($seg[0], $seg[1])));
            if (!$ok && !$db->val('SELECT COUNT(*) FROM bz_yonlendirmeler WHERE kaynak = ?', [Redirects::normalize($l)])) {
                $broken[] = [$l, $rows];
            }
            App::$lang = $oldLang;
        }
        self::view('seo', ['sorunlar' => $issues, 'kirik' => $broken, 'altsiz' => (int) $db->val("SELECT COUNT(*) FROM bz_medya WHERE mime LIKE 'image/%' AND (alt IS NULL OR alt = '')")]);
    }

    private static function yonlendirmeler(bool $post): void
    {
        $db = App::db();
        if ($post) {
            $a = $_POST['eylem'] ?? '';
            if ($a === 'ekle') {
                $from = trim((string) ($_POST['kaynak'] ?? ''));
                $to = trim((string) ($_POST['hedef'] ?? ''));
                if ($from === '' || ($to === '' && (int) ($_POST['kod'] ?? 301) !== 410) || (preg_match('#^[a-z]+:#i', $to) && !preg_match('#^https?://#i', $to))) {
                    bz_flash('Kaynak ve hedef adres gerekli (hedef / ile veya https:// ile başlamalı).', 'hata');
                } else {
                    Redirects::add($from, $to, (int) ($_POST['kod'] ?? 301));
                    $db->q('DELETE FROM bz_404 WHERE yol = ?', [Redirects::normalize($from)]);
                    bz_log('yonlendirme_ekle', "$from → $to");
                    bz_flash('Yönlendirme eklendi.');
                }
            } elseif ($a === 'sil') {
                $db->q('DELETE FROM bz_yonlendirmeler WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                bz_flash('Yönlendirme silindi.');
            } elseif ($a === '404_temizle') {
                $db->q('DELETE FROM bz_404');
                bz_flash('404 günlüğü temizlendi.');
            } elseif ($a === 'ice_aktar') {
                $n = 0;
                foreach (array_slice(preg_split('/\R/', (string) ($_POST['liste'] ?? '')) ?: [], 0, 5000) as $line) {
                    $parts = preg_split('/[\s,;]+/', trim($line));
                    if (count($parts) >= 2) {
                        Redirects::add($parts[0], $parts[1], (int) ($parts[2] ?? 301));
                        $n++;
                    }
                }
                bz_flash("$n yönlendirme içe aktarıldı.");
            }
            Cache::clear();
            bz_redirect(App::adminUrl('yonlendirmeler'));
        }
        self::view('yonlendirmeler', ['liste' => $db->all('SELECT * FROM bz_yonlendirmeler ORDER BY id DESC LIMIT 500'),
            'hatalar' => $db->all('SELECT * FROM bz_404 ORDER BY sayac DESC, son DESC LIMIT 100')]);
    }

    /* ------------------------------------------------------------ kullanıcılar ve profil */

    private static function kullanicilar(bool $post): void
    {
        $db = App::db();
        if ($post) {
            $a = $_POST['eylem'] ?? '';
            $id = (int) ($_POST['id'] ?? 0);
            if ($a === 'sil' && $id !== (int) Auth::user()['id']) {
                $db->q('DELETE FROM bz_kullanicilar WHERE id = ?', [$id]);
                bz_log('kullanici_sil', "#$id");
                bz_flash('Kullanıcı silindi.');
            } elseif ($a === '2fa_sifirla' && $id !== (int) Auth::user()['id']) {
                $db->q('UPDATE bz_kullanicilar SET totp_gizli = NULL, oturum_surumu = oturum_surumu + 1 WHERE id = ?', [$id]);
                bz_log('2fa_sifirla', "#$id");
                bz_flash('Kullanıcının iki adımlı doğrulaması sıfırlandı.');
            } elseif ($a === 'kaydet') {
                $ad = mb_substr(trim(strip_tags((string) ($_POST['ad'] ?? ''))), 0, 120);
                $email = Str::lower(trim((string) ($_POST['eposta'] ?? '')));
                $rol = array_key_exists($_POST['rol'] ?? '', Auth::ROLES) ? $_POST['rol'] : 'editor';
                $pass = (string) ($_POST['sifre'] ?? '');
                if ($ad === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    bz_flash('Ad ve geçerli bir e-posta gerekli.', 'hata');
                } elseif ((!$id || $pass !== '') && strlen($pass) < 10) {
                    bz_flash('Şifre en az 10 karakter olmalı.', 'hata');
                } elseif ($db->val('SELECT COUNT(*) FROM bz_kullanicilar WHERE eposta = ? AND id != ?', [$email, $id])) {
                    bz_flash('Bu e-posta zaten kayıtlı.', 'hata');
                } else {
                    $data = ['ad' => $ad, 'eposta' => $email, 'rol' => $id === (int) Auth::user()['id'] ? 'yonetici' : $rol];
                    if ($id) {
                        $db->update('bz_kullanicilar', $data, 'id = ?', [$id]);
                        if ($pass !== '') {
                            $db->q('UPDATE bz_kullanicilar SET sifre = ?, oturum_surumu = oturum_surumu + 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                        }
                    } else {
                        $db->insert('bz_kullanicilar', $data + ['sifre' => password_hash($pass, PASSWORD_DEFAULT), 'olusturma' => bz_now()]);
                    }
                    bz_log('kullanici_kaydet', $email);
                    bz_flash('Kullanıcı kaydedildi.');
                }
            }
            bz_redirect(App::adminUrl('kullanicilar'));
        }
        $edit = isset($_GET['id']) ? $db->one('SELECT id, ad, eposta, rol FROM bz_kullanicilar WHERE id = ?', [(int) $_GET['id']]) : null;
        self::view('kullanicilar', ['kullanicilar' => $db->all('SELECT id, ad, eposta, rol, son_giris, totp_gizli FROM bz_kullanicilar ORDER BY id'), 'duzenlenen' => $edit]);
    }

    private static function profil(bool $post): void
    {
        $u = Auth::user();
        $db = App::db();
        if ($post) {
            $a = $_POST['eylem'] ?? '';
            $full = $db->one('SELECT * FROM bz_kullanicilar WHERE id = ?', [$u['id']]);
            if ($a === 'sifre') {
                $new = (string) ($_POST['yeni'] ?? '');
                if (!password_verify((string) ($_POST['mevcut'] ?? ''), $full['sifre'])) {
                    bz_flash('Mevcut şifre hatalı.', 'hata');
                } elseif (strlen($new) < 10 || $new !== ($_POST['yeni2'] ?? '')) {
                    bz_flash('Yeni şifre en az 10 karakter olmalı ve iki alan eşleşmeli.', 'hata');
                } else {
                    $db->q('UPDATE bz_kullanicilar SET sifre = ?, oturum_surumu = oturum_surumu + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
                    $_SESSION['bz_surum'] = (int) $full['oturum_surumu'] + 1; // bu oturum açık kalır, diğerleri kapanır
                    bz_log('sifre_degistir');
                    bz_flash('Şifreniz güncellendi. Diğer cihazlardaki oturumlar kapatıldı.');
                }
            } elseif ($a === 'dil') {
                $l = in_array($_POST['dil'] ?? '', ['tr', 'en'], true) ? $_POST['dil'] : 'tr';
                $db->update('bz_kullanicilar', ['dil' => $l], 'id = ?', [$u['id']]);
                bz_flash($l === 'en' ? 'Panel language updated.' : 'Panel dili güncellendi.');
            } elseif ($a === '2fa_baslat') {
                $_SESSION['bz_totp_yeni'] = Totp::secret();
            } elseif ($a === '2fa_onayla') {
                $secret = $_SESSION['bz_totp_yeni'] ?? '';
                $step = $secret ? Totp::verifyStep($secret, (string) ($_POST['kod'] ?? '')) : null;
                if ($step !== null) {
                    // Kurulumda kullanılan kod tekrar kullanılamaz; sonraki kod hemen geçerlidir
                    $db->update('bz_kullanicilar', ['totp_gizli' => $secret, 'totp_son' => $step], 'id = ?', [$u['id']]);
                    unset($_SESSION['bz_totp_yeni']);
                    bz_log('2fa_ac');
                    bz_flash('İki adımlı doğrulama etkinleştirildi.');
                } else {
                    bz_flash('Kod doğrulanamadı. Telefonunuzun saatinin doğru olduğundan emin olun.', 'hata');
                }
            } elseif ($a === '2fa_kapat') {
                if (password_verify((string) ($_POST['mevcut'] ?? ''), $full['sifre'])) {
                    $db->update('bz_kullanicilar', ['totp_gizli' => null], 'id = ?', [$u['id']]);
                    bz_log('2fa_kapat');
                    bz_flash('İki adımlı doğrulama kapatıldı.');
                } else {
                    bz_flash('Şifre hatalı.', 'hata');
                }
            }
            bz_redirect(App::adminUrl('profil'));
        }
        $pending = $_SESSION['bz_totp_yeni'] ?? null;
        self::view('profil', ['bekleyen' => $pending, 'uri' => $pending ? Totp::uri($pending, $u['eposta'], App::setting('site_adi', 'BOZKURT CMS')) : null]);
    }

    /* ------------------------------------------------------------ ayarlar */

    public const SETTINGS = [
        'site_adi', 'site_slogan', 'site_aciklama', 'site_url', 'logo', 'favicon', 'varsayilan_resim', 'bakim_modu', 'arama_motoru_engelle',
        'diller', 'onbellek', 'onbellek_sure', 'cerez_bandi', 'cerez_metni', 'kvkk_sayfasi', 'ip_anonim', 'form_saklama_gun',
        'bildirim_eposta', 'smtp_sunucu', 'smtp_port', 'smtp_guvenlik', 'smtp_kullanici', 'smtp_sifre', 'smtp_gonderen',
        'google_dogrulama', 'yandex_dogrulama', 'google_analitik', 'yandex_metrika', 'meta_pixel', 'robots_ek',
        'api_acik', 'api_cors', 'azami_yukleme_mb', 'azami_resim_genislik',
        'yz_url', 'yz_anahtar', 'yz_model', 'yz_ton', 'yz_llms', 'yz_markdown', 'yz_botlari', 'llms_giris', 'mcp_acik',
        'odeme_saglayici', 'odeme_test', 'paytr_magaza_no', 'paytr_anahtar', 'paytr_tuz', 'iyzico_api', 'iyzico_gizli',
        'netgsm_kullanici', 'netgsm_sifre', 'netgsm_baslik', 'sms_bildirim_no', 'ek_tatiller',
        'isletme_adi', 'isletme_turu', 'isletme_telefon', 'isletme_adres', 'isletme_ilce', 'isletme_il', 'isletme_enlem', 'isletme_boylam', 'isletme_saatler',
        'webhook_adresleri', 'webhook_gizli', 'guvenilir_vekil', 'panel_adi', 'panel_logo',
    ];
    /** Boş gönderilirse mevcut değer korunur (şifreler formda gösterilmez) */
    private const SECRETS = ['smtp_sifre', 'yz_anahtar', 'paytr_anahtar', 'paytr_tuz', 'iyzico_gizli', 'netgsm_sifre', 'webhook_gizli'];
    private const CHECKBOXES = ['bakim_modu', 'arama_motoru_engelle', 'onbellek', 'cerez_bandi', 'ip_anonim', 'api_acik', 'yz_llms', 'yz_markdown', 'mcp_acik', 'odeme_test', 'guvenilir_vekil'];

    private static function ayarlar(bool $post): void
    {
        if ($post) {
            $a = $_POST['eylem'] ?? 'kaydet';
            if ($a === 'onbellek') {
                Cache::clearAll();
                bz_flash('Önbellek temizlendi.');
            } elseif ($a === 'smtp_test') {
                $ok = Mailer::send(Auth::user()['eposta'], 'BOZKURT CMS test e-postası', '<p>Merhaba! SMTP ayarlarınız çalışıyor. 🐺</p>');
                bz_flash($ok ? 'Test e-postası ' . Auth::user()['eposta'] . ' adresine gönderildi.' : 'Gönderilemedi: ' . (Mailer::$lastError ?: 'mail() başarısız'), $ok ? 'basari' : 'hata');
            } elseif ($a === 'yz_test') {
                $r = Ai::task('ozet', 'BOZKURT CMS, tasarımcılar için Türkçe, hızlı ve güvenli bir içerik yönetim sistemidir.');
                bz_flash($r['ok'] ? 'YZ bağlantısı çalışıyor: “' . mb_substr((string) $r['sonuc'], 0, 120) . '”' : 'YZ hatası: ' . $r['hata'], $r['ok'] ? 'basari' : 'hata');
            } elseif ($a === 'sms_test') {
                $ok = Sms::send((string) App::setting('sms_bildirim_no', ''), 'BOZKURT CMS test mesaji');
                bz_flash($ok ? 'Test SMS\'i gönderildi.' : 'SMS gönderilemedi. Netgsm bilgilerini ve bildirim numarasını kontrol edin.', $ok ? 'basari' : 'hata');
            } else {
                $vals = [];
                foreach (self::SETTINGS as $k) {
                    if (in_array($k, self::SECRETS, true) && ($_POST[$k] ?? '') === '') {
                        continue;
                    }
                    $vals[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 5000);
                }
                foreach (self::CHECKBOXES as $cb) {
                    $vals[$cb] = !empty($_POST[$cb]) ? '1' : '0';
                }
                foreach (['logo', 'favicon', 'varsayilan_resim', 'panel_logo'] as $img) {
                    if ($vals[$img] !== '' && (!str_starts_with($vals[$img], 'yuklemeler/') || str_contains($vals[$img], '..'))) {
                        $vals[$img] = '';
                    }
                }
                $langs = array_values(array_unique(array_filter(array_map(fn($l) => strtolower(trim($l)), explode(',', $vals['diller'])), fn($l) => isset(App::LANG_NAMES[$l]))));
                $vals['diller'] = implode(',', $langs ?: ['tr']);
                $vals['site_url'] = filter_var($vals['site_url'], FILTER_VALIDATE_URL) && preg_match('#^https?://#', $vals['site_url']) ? rtrim($vals['site_url'], '/') : App::siteUrl();
                foreach (['google_analitik' => '/^G-[A-Z0-9]+$/', 'yandex_metrika' => '/^\d+$/', 'meta_pixel' => '/^\d+$/'] as $k => $re) {
                    if ($vals[$k] !== '' && !preg_match($re, $vals[$k])) {
                        $vals[$k] = '';
                        bz_flash("“{$k}” biçimi geçersiz olduğu için kaydedilmedi.", 'hata');
                    }
                }
                if ($vals['yz_url'] !== '' && !preg_match('#^(https://|http://(localhost|127\.0\.0\.1)[:/])#', $vals['yz_url'])) {
                    $vals['yz_url'] = 'https://api.openai.com/v1';
                }
                App::saveSettings($vals);
                Cache::clearAll();
                bz_log('ayarlar_guncelle');
                bz_flash('Ayarlar kaydedildi.');
            }
            bz_redirect(App::adminUrl('ayarlar') . (isset($_POST['_sekme']) ? '#' . preg_replace('/[^a-z]/', '', (string) $_POST['_sekme']) : ''));
        }
        $vals = [];
        foreach (self::SETTINGS as $k) {
            $vals[$k] = (string) App::setting($k, '');
        }
        foreach (['onbellek' => '1', 'cerez_bandi' => '1', 'ip_anonim' => '1', 'yz_llms' => '1', 'yz_markdown' => '1', 'odeme_test' => '1',
            'yz_botlari' => 'sadece_arama', 'diller' => App::defaultLang(), 'site_url' => App::siteUrl()] as $k => $d) {
            $vals[$k] = (string) App::setting($k, $d);
        }
        self::view('ayarlar', ['a' => $vals]);
    }

    /* ------------------------------------------------------------ API anahtarları */

    private static function api(bool $post): void
    {
        $new = null;
        if ($post) {
            if (($_POST['eylem'] ?? '') === 'olustur') {
                $new = Tokens::create(trim((string) ($_POST['ad'] ?? '')), (string) ($_POST['kapsam'] ?? 'oku'));
            } elseif (($_POST['eylem'] ?? '') === 'sil') {
                App::db()->q('DELETE FROM bz_tokenlar WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
                bz_log('token_sil', '#' . (int) ($_POST['id'] ?? 0));
                bz_flash('Anahtar iptal edildi.');
                bz_redirect(App::adminUrl('api'));
            }
        }
        self::view('api', ['tokenlar' => App::db()->all('SELECT id, ad, kapsam, son_kullanim, olusturma FROM bz_tokenlar ORDER BY id DESC'), 'yeni' => $new]);
    }

    /* ------------------------------------------------------------ araçlar */

    private static function araclar(bool $post): void
    {
        $result = null;
        if (isset($_GET['statik'])) {
            Tools::exportStatic();
        }
        if ($post) {
            $a = $_POST['eylem'] ?? '';
            if ($a === 'wp') {
                $f = $_FILES['wxr'] ?? [];
                if (($f['error'] ?? 4) === UPLOAD_ERR_OK) {
                    $result = Tools::importWordPress($f['tmp_name'], (string) ($_POST['sablon'] ?? ''), [
                        'icerik' => preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['icerik_alani'] ?? '')),
                        'ozet' => preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['ozet_alani'] ?? '')),
                        'kategori' => preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['kategori_alani'] ?? '')),
                        'turler' => preg_replace('/[^a-z0-9_,]/', '', (string) ($_POST['turler'] ?? 'post')),
                    ], !empty($_POST['yonlendir']));
                } else {
                    bz_flash('WXR dosyası yüklenemedi.', 'hata');
                }
            } elseif ($a === 'guncelle') {
                $r = Tools::update();
                bz_flash($r === true ? 'BOZKURT CMS güncellendi.' : $r, $r === true ? 'basari' : 'hata');
                bz_redirect(App::adminUrl('araclar'));
            }
        }
        $release = isset($_GET['kontrol']) ? Tools::latestRelease() : null;
        self::view('araclar', ['sonuc' => $result, 'surum' => $release, 'kontrol' => isset($_GET['kontrol'])]);
    }

    /* ------------------------------------------------------------ yedek, günlük, sistem */

    private static function yedek(bool $post): void
    {
        if (isset($_GET['indir'])) {
            Backup::download(isset($_GET['dosyalar']));
        }
        if ($post && ($_POST['eylem'] ?? '') === 'geri_yukle') {
            $r = Backup::restore($_FILES['yedek'] ?? []);
            bz_flash($r === true ? 'Yedek geri yüklendi.' : $r, $r === true ? 'basari' : 'hata');
            bz_redirect(App::adminUrl('yedek'));
        }
        self::view('yedek');
    }

    private static function gunluk(bool $post): void
    {
        $rows = App::db()->all('SELECT g.*, k.ad FROM bz_gunluk g LEFT JOIN bz_kullanicilar k ON k.id = g.kullanici_id ORDER BY g.id DESC LIMIT 300');
        self::view('gunluk', ['kayitlar' => $rows]);
    }

    private static function sistem(bool $post): void
    {
        self::view('sistem', ['kontroller' => Installer::requirements()]);
    }
}
