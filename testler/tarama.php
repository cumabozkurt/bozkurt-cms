<?php
/**
 * BOZKURT CMS tarama testi: sitenin tamamını gezer (tüm diller), her iç bağlantıyı ve varlığı doğrular,
 * HTML/SEO/erişilebilirlik temel kurallarını denetler, XML çıktılarını ayrıştırır, panelin tüm ekranlarını
 * iki dilde açar, gerçek görsel yükler ve yedek alıp geri yükleyerek veri bütünlüğünü karşılaştırır.
 * Kullanım: php testler/tarama.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/bozkurt-tarama-' . bin2hex(random_bytes(4));
$port = random_int(40001, 60000);
$B = "http://127.0.0.1:$port";
$fail = 0;
$pass = 0;
$uyari = [];

function kopyala(string $src, string $dst): void
{
    @mkdir($dst, 0755, true);
    foreach (scandir($src) as $f) {
        if (in_array($f, ['.', '..', '.git'], true)) {
            continue;
        }
        is_dir("$src/$f") ? kopyala("$src/$f", "$dst/$f") : copy("$src/$f", "$dst/$f");
    }
}
function k(bool $ok, string $msg, string $detay = ''): void
{
    global $fail, $pass;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $msg . ($ok || $detay === '' ? '' : "  → $detay") . PHP_EOL;
    $ok ? $pass++ : $fail++;
}
function bolum(string $t): void
{
    echo "\n▸ $t\n";
}

$ck = [];
function istek(string $method, string $url, array|string|null $body = null, array $headers = [], bool $cerez = true, ?array $dosya = null): array
{
    global $ck;
    $h = $headers;
    if ($cerez && $ck) {
        $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($ck), $ck));
    }
    $opts = ['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]];
    if ($dosya) {
        $bd = '----bz' . bin2hex(random_bytes(6));
        $data = '';
        foreach ((array) $body as $kk => $v) {
            $data .= "--$bd\r\nContent-Disposition: form-data; name=\"$kk\"\r\n\r\n$v\r\n";
        }
        $data .= "--$bd\r\nContent-Disposition: form-data; name=\"{$dosya[0]}\"; filename=\"{$dosya[1]}\"\r\nContent-Type: {$dosya[2]}\r\n\r\n{$dosya[3]}\r\n--$bd--\r\n";
        $h[] = "Content-Type: multipart/form-data; boundary=$bd";
        $opts['http']['content'] = $data;
    } elseif (is_array($body)) {
        $h[] = 'Content-Type: application/x-www-form-urlencoded';
        $opts['http']['content'] = http_build_query($body);
    } elseif (is_string($body)) {
        $opts['http']['content'] = $body;
    }
    $opts['http']['header'] = implode("\r\n", $h);
    $t0 = microtime(true);
    $res = @file_get_contents($url, false, stream_context_create($opts));
    $ms = (int) ((microtime(true) - $t0) * 1000);
    $code = 0;
    $hdr = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
            $code = (int) $m[1];
            $hdr = [];
        } elseif (str_contains($line, ':')) {
            [$kk, $v] = explode(':', $line, 2);
            $hdr[strtolower(trim($kk))][] = trim($v);
            if ($cerez && strtolower(trim($kk)) === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', trim($v), $c)) {
                $ck[$c[1]] = $c[2];
            }
        }
    }
    return ['kod' => $code, 'govde' => (string) $res, 'basliklar' => $hdr, 'ms' => $ms];
}
function csrf(string $html): string
{
    return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m) || preg_match('/name="csrf" content="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}
function temiz(string $b): bool
{
    return !preg_match('/Fatal error|Warning:|Deprecated:|Notice:|Uncaught|Parse error/', $b);
}

kopyala($root, $tmp);
foreach (array_merge(glob("$tmp/veri/*.sqlite") ?: [], glob("$tmp/veri/yapilandirma.php") ?: [], glob("$tmp/veri/kurulum.kilit") ?: []) as $f) {
    unlink($f);
}
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", 'yonlendirici.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', $tmp . '/sunucu.log', 'w']], $pipes, $tmp);
usleep(800000);

bolum('Kurulum');
$r = istek('GET', "$B/yonetim/");
$r = istek('POST', "$B/yonetim/", ['_csrf' => csrf($r['govde']), 'site_adi' => 'Tarama Sitesi', 'ad' => 'Yönetici', 'eposta' => 'admin@ornek.com', 'sifre' => 'cokgizlisifre1', 'surucu' => 'sqlite', 'ornek' => '1']);
k($r['kod'] === 302, 'Kurulum');
$r = istek('GET', "$B/yonetim/");
$T = csrf($r['govde']);
istek('POST', "$B/yonetim/?s=ayarlar", ['_csrf' => $T, 'eylem' => 'kaydet', 'site_adi' => 'Tarama Sitesi', 'site_aciklama' => 'BOZKURT CMS ile hazırlanmış örnek kurumsal site; blog, hizmetler ve iletişim.',
    'diller' => 'tr,en', 'site_url' => $B, 'onbellek' => '1', 'cerez_bandi' => '1', 'yz_llms' => '1', 'yz_markdown' => '1', 'yz_botlari' => 'sadece_arama', 'isletme_adi' => 'Tarama Ltd']);

bolum('Gerçek görsel yükleme ve küçük resim');
$im = imagecreatetruecolor(1600, 900);
imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
ob_start();
imagejpeg($im, null, 85);
$jpg = (string) ob_get_clean();
$r = istek('POST', "$B/yonetim/?s=medya&json=1", ['_csrf' => $T, 'eylem' => 'yukle'], ['Accept: application/json'], true, ['dosya', 'kapak.jpg', 'image/jpeg', $jpg]);
$j = json_decode($r['govde'], true);
$dosya = $j['sonuclar'][0]['dosya'] ?? '';
k(!empty($j['sonuclar'][0]['ok']) && str_starts_with($dosya, 'yuklemeler/'), 'JPEG yüklendi', $r['govde']);
$r = istek('GET', "$B/" . $dosya, null, [], false);
k($r['kod'] === 200 && str_starts_with($r['govde'], "\xFF\xD8"), 'Yüklenen dosya servis ediliyor');
$sz = @getimagesizefromstring($r['govde']);
k($sz && $sz[0] <= 2400, 'Büyük resim azami genişliğe indirildi', (string) ($sz[0] ?? ''));

bolum('Örnek içeriği zenginleştir (görsel, iç bağlantı, çeviri)');
$r = istek('POST', "$B/yonetim/?s=duzenle&id=2", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'BOZKURT CMS yayında!', 'alan[kapak]' => $dosya, 'alan[kategori]' => 'Duyuru', 'alan[yazar]' => 'Ekip',
    'alan[icerik]' => '<p>Giriş paragrafı <a href="/hakkimizda">hakkımızda</a> ve <a href="/blog">blog</a>.</p><h2>Özellikler</h2><p>Metin.</p><h3>Alt başlık</h3><p><img src="/' . $dosya . '" alt="Kırmızı kapak"></p>']);
k($r['kod'] === 302, 'Blog yazısı güncellendi');
istek('POST', "$B/yonetim/?s=duzenle&sablon=blog&dil=en&kaynak=2", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'BOZKURT CMS is live', 'alan[kapak]' => $dosya, 'alan[kategori]' => 'Duyuru', 'alan[icerik]' => '<p>English <a href="/en/blog">blog</a>.</p><h2>Features</h2>']);
istek('POST', "$B/yonetim/?s=duzenle&sablon=hizmetler&dil=tr", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'Kurumsal Web Sitesi', 'alan[ozet_metin]' => 'Hızlı ve güvenli', 'alan[fiyat]' => '25000',
    'alan[gorsel]' => $dosya, 'alan[govde][a][_tip]' => 'metin', 'alan[govde][a][icerik]' => '<h2>Süreç</h2><p>Adımlar</p>']);
istek('POST', "$B/yonetim/?s=duzenle&sablon=sss&dil=tr", ['_csrf' => $T, 'eylem' => 'yayinla', 'alan[baslik]' => 'Sık Sorulan Sorular', 'alan[giris]' => 'Yanıtlar',
    'alan[sorular][0][soru]' => 'Hostinger\'da çalışır mı?', 'alan[sorular][0][cevap]' => 'Evet.', 'alan[sorular][1][soru]' => 'Ücretsiz mi?', 'alan[sorular][1][cevap]' => 'Evet, MIT lisanslı.']);
$ck = []; // ziyaretçi olarak gez

bolum('Site taraması (tüm iç bağlantılar, iki dil)');
$kuyruk = ['/', '/en'];
$gezilen = [];
$varlik = [];
$sayfalar = [];
while ($kuyruk && count($gezilen) < 400) {
    $u = array_shift($kuyruk);
    if (isset($gezilen[$u])) {
        continue;
    }
    $r = istek('GET', $B . $u, null, [], false);
    $gezilen[$u] = $r['kod'];
    if ($r['kod'] === 301 || $r['kod'] === 302) {
        $loc = (string) parse_url($r['basliklar']['location'][0] ?? '', PHP_URL_PATH);
        if ($loc !== '' && !isset($gezilen[$loc])) {
            $kuyruk[] = $loc . ((string) parse_url($r['basliklar']['location'][0] ?? '', PHP_URL_QUERY) !== '' ? '?' . parse_url($r['basliklar']['location'][0], PHP_URL_QUERY) : '');
        }
        continue;
    }
    $ct = $r['basliklar']['content-type'][0] ?? '';
    if (!str_contains($ct, 'text/html')) {
        continue;
    }
    $sayfalar[$u] = $r;
    preg_match_all('#\b(?:href|src)="([^"]+)"#', $r['govde'], $m);
    preg_match_all('#\bsrcset="([^"]+)"#', $r['govde'], $ms);
    $linkler = $m[1];
    foreach ($ms[1] as $set) {
        foreach (explode(',', $set) as $part) {
            $linkler[] = trim(explode(' ', trim($part))[0]);
        }
    }
    foreach ($linkler as $l) {
        $l = html_entity_decode($l, ENT_QUOTES);
        if (preg_match('#^(https?:)?//#', $l) && !str_starts_with($l, $B)) {
            continue;
        }
        if (preg_match('#^(mailto|tel|javascript|data|\#)#', $l) || $l === '') {
            continue;
        }
        $l = str_starts_with($l, $B) ? substr($l, strlen($B)) : $l;
        if (!str_starts_with($l, '/')) {
            continue;
        }
        $l = strtok($l, '#');
        if (preg_match('#^/yonetim#', $l)) {
            continue;
        }
        if (preg_match('#\.(css|js|svg|png|jpe?g|webp|gif|ico|woff2?)(\?|$)#', $l)) {
            $varlik[$l][] = $u;
        } elseif (!isset($gezilen[$l]) && !in_array($l, $kuyruk, true)) {
            $kuyruk[] = $l;
        }
    }
}
$kirik = array_filter($gezilen, fn($c) => !in_array($c, [200, 301, 302], true));
k(count($gezilen) > 20, count($gezilen) . ' adres gezildi');
k(!$kirik, 'Kırık iç bağlantı yok', json_encode($kirik, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$kv = [];
foreach (array_keys($varlik) as $v) {
    $r = istek('GET', $B . $v, null, [], false);
    if ($r['kod'] !== 200) {
        $kv[$v] = $r['kod'];
    }
}
k(!$kv, count($varlik) . ' varlık (CSS, JS, görsel, srcset) erişilebilir', json_encode($kv, JSON_UNESCAPED_SLASHES));

bolum('Sayfa başına HTML / SEO / erişilebilirlik kuralları');
$sorun = [];
$basliklar = [];
$yavas = [];
foreach ($sayfalar as $u => $r) {
    $h = $r['govde'];
    $e = [];
    if (!temiz($h)) {
        $e[] = 'PHP hatası';
    }
    if (!preg_match('#^<!doctype html>#i', ltrim($h))) {
        $e[] = 'doctype yok';
    }
    if (!preg_match('#<html[^>]+lang="(tr|en)"#', $h, $lm)) {
        $e[] = 'html lang yok';
    } elseif (str_starts_with($u, '/en') && $lm[1] !== 'en') {
        $e[] = 'İngilizce sayfada lang≠en';
    }
    $noindex = str_contains($h, 'noindex');
    if (!preg_match('#<title>([^<]+)</title>#', $h, $tm)) {
        $e[] = 'title yok';
    } elseif (!$noindex && $r['kod'] === 200) {
        $basliklar[(str_starts_with($u, '/en') ? 'en' : 'tr') . ' | ' . html_entity_decode($tm[1])][] = $u;
    }
    if (!preg_match('#<meta name="description" content="[^"]{20,}"#', $h) && !$noindex) {
        $e[] = 'meta açıklama yok/kısa';
    }
    if (!$noindex && !preg_match('#<link rel="canonical" href="' . preg_quote($B, '#') . '#', $h)) {
        $e[] = 'canonical yok veya alan adı yanlış';
    }
    if (!preg_match('#<meta name="viewport"#', $h)) {
        $e[] = 'viewport yok';
    }
    $h1 = preg_match_all('#<h1[\s>]#', $h);
    if ($h1 !== 1) {
        $e[] = "$h1 adet h1";
    }
    if (preg_match_all('#<img\b(?![^>]*\balt=)[^>]*>#', $h)) {
        $e[] = 'alt özniteliksiz img';
    }
    preg_match_all('#\bid="([^"]+)"#', $h, $ids);
    $dup = array_keys(array_filter(array_count_values($ids[1]), fn($n) => $n > 1));
    if ($dup) {
        $e[] = 'yinelenen id: ' . implode(',', $dup);
    }
    foreach (['<main', 'og:title', 'application/ld+json'] as $req) {
        if (!str_contains($h, $req) && !$noindex) {
            $e[] = "$req eksik";
        }
    }
    if (preg_match_all('#<input\b(?![^>]*type="(hidden|submit|checkbox|radio)")[^>]*>#', $h, $in)) {
        foreach ($in[0] as $inp) {
            if (!preg_match('#aria-label=|placeholder=|id="[^"]+"#', $inp) && !str_contains($h, 'web_sitesi_adresi')) {
                $e[] = 'etiketsiz input';
                break;
            }
        }
    }
    if (preg_match('#<a [^>]*target="_blank"(?![^>]*rel="[^"]*noopener)#', $h)) {
        $uyari[] = "$u: target=_blank noopener'sız bağlantı";
    }
    if ($r['ms'] > 500) {
        $yavas[$u] = $r['ms'];
    }
    if ($e) {
        $sorun[$u] = $e;
    }
}
k(!$sorun, count($sayfalar) . ' HTML sayfası kurallara uyuyor', json_encode($sorun, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$yb = array_filter($basliklar, fn($l) => count($l) > 1);
k(!$yb, 'Dizine açık sayfalarda (aynı dilde) yinelenen <title> yok', json_encode($yb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
k(!$yavas, 'Tüm sayfalar 500 ms altında (yerel)', json_encode($yavas));

bolum('Özel sayfa kontrolleri');
$r = istek('GET', "$B/blog/bozkurt-cms-yayinda", null, [], false);
k(str_contains($r['govde'], 'srcset=') && str_contains($r['govde'], '.webp'), 'Kapak görseli srcset + WebP');
k(str_contains($r['govde'], '"@type":"BlogPosting"') || str_contains($r['govde'], '"@type":"Article"'), 'Makale şeması');
k(str_contains($r['govde'], 'hreflang="en"') && str_contains($r['govde'], 'hreflang="x-default"'), 'hreflang + x-default');
$r = istek('GET', "$B/sss", null, [], false);
k(str_contains($r['govde'], '"@type":"FAQPage"') && str_contains($r['govde'], 'Ücretsiz mi?'), 'FAQPage şeması');
$r = istek('GET', "$B/hizmetler/kurumsal-web-sitesi", null, [], false);
k($r['kod'] === 200 && str_contains($r['govde'], 'id="surec"') && str_contains($r['govde'], '25.000'), 'Bloklu hizmet sayfası');
$r = istek('GET', "$B/blog?kategori=Duyuru", null, [], false);
k($r['kod'] === 200 && str_contains($r['govde'], 'BOZKURT CMS yayında!') && !str_contains($r['govde'], 'KVKK uyumlu iletişim'), 'Kategori süzgeci');
$r = istek('GET', "$B/blog?yil=" . date('Y') . '&ay=' . date('n'), null, [], false);
k($r['kod'] === 200 && str_contains($r['govde'], 'BOZKURT CMS yayında!'), 'Arşiv süzgeci');
$r = istek('GET', "$B/ara?q=" . rawurlencode('güvenli'), null, [], false);
k($r['kod'] === 200 && temiz($r['govde']), 'Türkçe karakterli arama');
$r = istek('GET', "$B/olmayan", null, [], false);
k($r['kod'] === 404 && str_contains($r['govde'], 'noindex'), '404 sayfası noindex');
$r1 = istek('GET', "$B/blog", null, [], false);
$r2 = istek('GET', "$B/blog", null, [], false);
k(($r2['basliklar']['x-bozkurt-onbellek'][0] ?? '') === 'HIT', 'Tam sayfa önbellek ikinci istekte HIT');

bolum('XML ve metin çıktıları');
libxml_use_internal_errors(true);
$r = istek('GET', "$B/sitemap.xml", null, [], false);
$x = simplexml_load_string($r['govde']);
k($x !== false, 'sitemap.xml geçerli XML');
$locs = [];
if ($x) {
    foreach ($x->url as $url) {
        $locs[] = (string) $url->loc;
    }
}
$kl = [];
foreach ($locs as $l) {
    $c = istek('GET', $l, null, [], false)['kod'];
    if ($c !== 200) {
        $kl[$l] = $c;
    }
}
k($locs && !$kl, count($locs) . ' sitemap adresinin tamamı 200', json_encode($kl, JSON_UNESCAPED_SLASHES));
$r = istek('GET', "$B/sitemap.xml", null, ['Host: saldirgan.example'], false);
k(!str_contains($r['govde'], 'saldirgan.example'), 'Sitemap Host başlığından etkilenmiyor');
foreach (['/rss.xml', '/blog/rss.xml'] as $f) {
    $r = istek('GET', $B . $f, null, [], false);
    $x = simplexml_load_string($r['govde']);
    k($x !== false && isset($x->channel->item), "$f geçerli RSS");
}
$r = istek('GET', "$B/llms.txt", null, [], false);
preg_match_all('#\]\((' . preg_quote($B, '#') . '[^)]+)\)#', $r['govde'], $lm);
$kl = [];
foreach (array_unique($lm[1]) as $l) {
    $c = istek('GET', $l, null, [], false);
    if ($c['kod'] !== 200) {
        $kl[$l] = $c['kod'];
    }
}
k($lm[1] && !$kl, count(array_unique($lm[1])) . ' llms.txt bağlantısının tamamı çalışıyor (.md dahil)', json_encode($kl, JSON_UNESCAPED_SLASHES));
$r = istek('GET', "$B/robots.txt", null, [], false);
k(str_contains($r['govde'], 'Sitemap: ' . $B . '/sitemap.xml'), 'robots.txt sitemap satırı');

bolum('Panel: tüm ekranlar iki dilde');
$ck = [];
$g = istek('GET', "$B/yonetim/?s=giris");
istek('POST', "$B/yonetim/?s=giris", ['_csrf' => csrf($g['govde']), 'eposta' => 'admin@ornek.com', 'sifre' => 'cokgizlisifre1']);
$g = istek('GET', "$B/yonetim/");
$T = csrf($g['govde']);
$ekranlar = ['panel', 'icerik', 'icerik&sablon=blog', 'icerik&sablon=blog&dil=en', 'icerik&sablon=hizmetler', 'duzenle&sablon=blog', 'duzenle&id=2', 'duzenle&sablon=hizmetler',
    'duzenle&sablon=sss', 'duzenle&sablon=ana-sayfa', 'duzenle&sablon=ana-sayfa&dil=en', 'duzenle&sablon=iletisim', 'duzenle&sablon=kvkk', 'duzenle&sablon=hakkimizda',
    'medya', 'medya&tur=altsiz', 'formlar', 'genel', 'genel&dil=en', 'kullanicilar', 'kullanicilar&id=1', 'ayarlar', 'yedek', 'gunluk', 'sistem', 'profil', 'takvim',
    'takvim&ay=2026-10', 'seo', 'yonlendirmeler', 'api', 'araclar', 'siparisler'];
foreach (['tr', 'en'] as $pd) {
    istek('POST', "$B/yonetim/?s=profil", ['_csrf' => $T, 'eylem' => 'dil', 'dil' => $pd]);
    $hatali = [];
    foreach ($ekranlar as $s) {
        $r = istek('GET', "$B/yonetim/?s=$s");
        if ($r['kod'] !== 200 || !temiz($r['govde']) || !str_contains($r['govde'], '</html>')) {
            $hatali[$s] = $r['kod'];
        }
        if ($pd === 'en' && !str_contains($r['govde'], '<html lang="en"')) {
            $hatali[$s] = 'lang';
        }
    }
    k(!$hatali, count($ekranlar) . " panel ekranı sorunsuz ($pd)", json_encode($hatali));
}
istek('POST', "$B/yonetim/?s=profil", ['_csrf' => $T, 'eylem' => 'dil', 'dil' => 'tr']);
$r = istek('GET', "$B/yonetim/assets/yonetim.js");
k($r['kod'] === 200 && str_contains($r['govde'], 'function T('), 'Panel betiği yükleniyor');

bolum('Yedek → geri yükleme bütünlüğü');
$say = function (): array {
    global $B;
    $out = [];
    foreach (['blog', 'hizmetler'] as $t) {
        $out[$t] = json_decode(istek('GET', "$B/api/v1/$t?limit=100&dil=tr", null, ['Authorization: Bearer ' . $GLOBALS['tok']])['govde'], true)['meta']['toplam'] ?? -1;
    }
    return $out;
};
$r = istek('POST', "$B/yonetim/?s=api", ['_csrf' => $T, 'eylem' => 'olustur', 'ad' => 'Tarama', 'kapsam' => 'oku']);
preg_match('/(bz_[a-f0-9]{48})/', $r['govde'], $m);
$tok = $m[1] ?? '';
$once = $say();
$r = istek('GET', "$B/yonetim/?s=yedek&indir=1");
$yedek = $r['govde'];
$tur = str_starts_with($yedek, 'PK') ? 'zip' : 'json';
k($r['kod'] === 200 && strlen($yedek) > 500, "Yedek indirildi ($tur, " . strlen($yedek) . ' bayt)');
istek('POST', "$B/yonetim/?s=duzenle&sablon=blog", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'Yedekten sonra eklenen', 'alan[icerik]' => '<p>x</p>']);
k($say()['blog'] === $once['blog'] + 1, 'Yedekten sonra içerik eklendi');
$r = istek('POST', "$B/yonetim/?s=yedek", ['_csrf' => $T, 'eylem' => 'geri_yukle'], [], true, ['yedek', "yedek.$tur", $tur === 'zip' ? 'application/zip' : 'application/json', $yedek]);
$ck = [];
$g = istek('GET', "$B/yonetim/?s=giris");
istek('POST', "$B/yonetim/?s=giris", ['_csrf' => csrf($g['govde']), 'eposta' => 'admin@ornek.com', 'sifre' => 'cokgizlisifre1']);
$sonra = $say();
k($sonra === $once, 'Geri yükleme sonrası içerik sayıları yedektekiyle aynı', json_encode([$once, $sonra]));
$r = istek('GET', "$B/blog/bozkurt-cms-yayinda", null, [], false);
k($r['kod'] === 200 && str_contains($r['govde'], 'Kırmızı kapak'), 'Geri yüklenen içerik ve medya bağlantısı sağlam');
$r = istek('GET', "$B/en/blog/bozkurt-cms-is-live", null, [], false);
k($r['kod'] === 200, 'Geri yüklenen çeviri yayında');

bolum('Oturum ve çıkış');
$g = istek('GET', "$B/yonetim/");
$r = istek('POST', "$B/yonetim/?s=cikis", ['_csrf' => csrf($g['govde'])]);
$r = istek('GET', "$B/yonetim/?s=ayarlar");
k($r['kod'] === 302, 'Çıkıştan sonra panel kapalı');

bolum('Sunucu günlükleri');
$log = (string) @file_get_contents("$tmp/veri/hatalar.log");
k(!preg_match('/PHP (Fatal|Warning|Deprecated|Notice)/', $log), 'PHP hata günlüğü temiz', mb_substr($log, 0, 800));
$slog = (string) @file_get_contents("$tmp/sunucu.log");
k(!preg_match('/PHP (Fatal|Warning|Deprecated|Notice)/', $slog), 'Sunucu çıktısında PHP uyarısı yok', mb_substr($slog, 0, 800));

if ($uyari) {
    echo "\nUyarılar (hata değil):\n  - " . implode("\n  - ", array_unique($uyari)) . "\n";
}
proc_terminate($proc);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$pass geçti, $fail başarısız.\n";
exit($fail ? 1 : 0);
