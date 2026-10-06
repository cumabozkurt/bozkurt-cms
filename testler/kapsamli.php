<?php
/**
 * BOZKURT CMS kapsamlı uçtan uca test: kurulum, çok dil, API, MCP, YZ çıktıları, yönlendirme,
 * bloklar, satır içi düzenleme, içe/dışa aktarma ve güvenlik regresyonları.
 * Kullanım: php testler/kapsamli.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/bozkurt-kapsamli-' . bin2hex(random_bytes(4));
$port = random_int(20000, 40000);
$B = "http://127.0.0.1:$port";
$fail = 0;
$pass = 0;

function kopyala(string $src, string $dst): void
{
    @mkdir($dst, 0755, true);
    foreach (scandir($src) as $f) {
        if (in_array($f, ['.', '..', '.git'], true)) {
            continue;
        }
        is_dir("$src/$f") ? kopyala("$src/$f", "$dst/$f") : copy("$src/$f", "$dst/$f");
    }
    foreach (glob("$dst/veri/*.sqlite") ?: [] as $f) {
        unlink($f);
    }
}

final class Istemci
{
    public array $cerez = [];
    public array $son = [];

    public function istek(string $method, string $url, array|string|null $body = null, array $headers = []): array
    {
        $h = $headers;
        if ($this->cerez) {
            $h[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cerez), $this->cerez));
        }
        $opts = ['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]];
        if (is_array($body)) {
            $hasFile = (bool) array_filter($body, fn($v) => $v instanceof CURLFile);
            if ($hasFile) {
                $bd = '----bz' . bin2hex(random_bytes(6));
                $data = '';
                foreach ($body as $k => $v) {
                    if ($v instanceof CURLFile) {
                        $data .= "--$bd\r\nContent-Disposition: form-data; name=\"$k\"; filename=\"" . basename($v->getPostFilename() ?: $v->getFilename()) . "\"\r\nContent-Type: " . ($v->getMimeType() ?: 'application/octet-stream') . "\r\n\r\n" . file_get_contents($v->getFilename()) . "\r\n";
                    } else {
                        $data .= "--$bd\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
                    }
                }
                $data .= "--$bd--\r\n";
                $h[] = "Content-Type: multipart/form-data; boundary=$bd";
                $opts['http']['content'] = $data;
            } else {
                $h[] = 'Content-Type: application/x-www-form-urlencoded';
                $opts['http']['content'] = http_build_query($body);
            }
        } elseif (is_string($body)) {
            $opts['http']['content'] = $body;
        }
        $opts['http']['header'] = implode("\r\n", $h);
        $res = @file_get_contents($url, false, stream_context_create($opts));
        $code = 0;
        $hdr = [];
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
                $code = (int) $m[1];
                $hdr = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $hdr[strtolower(trim($k))][] = trim($v);
                if (strtolower(trim($k)) === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', trim($v), $c)) {
                    $this->cerez[$c[1]] = $c[2];
                }
            }
        }
        return $this->son = ['kod' => $code, 'govde' => (string) $res, 'basliklar' => $hdr];
    }

    public function csrf(string $html): string
    {
        return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m) || preg_match('/name="csrf" content="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }
}

function k(bool $ok, string $msg, string $detay = ''): void
{
    global $fail, $pass;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $msg . ($ok || $detay === '' ? '' : "  → $detay") . PHP_EOL;
    $ok ? $pass++ : $fail++;
}
function temiz(string $b): bool
{
    return !preg_match('/Fatal error|Warning:|Deprecated:|Notice:|Uncaught/', $b);
}
function bolum(string $t): void
{
    echo "\n▸ $t\n";
}

// Veritabanı: varsayılan SQLite. MySQL için: BZ_TEST_MYSQL="sunucu;port;veritabani;kullanici;sifre" php testler/kapsamli.php
$dbAyar = ['surucu' => 'sqlite'];
if ($my = getenv('BZ_TEST_MYSQL')) {
    [$h, $pt, $dn, $du, $dp] = array_pad(explode(';', $my), 5, '');
    $pdo = new PDO("mysql:host=$h;port=$pt;dbname=$dn;charset=utf8mb4", $du, $dp);
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec("DROP TABLE `$t`");
    }
    $dbAyar = ['surucu' => 'mysql', 'db_sunucu' => $h, 'db_port' => $pt, 'db_ad' => $dn, 'db_kullanici' => $du, 'db_sifre' => $dp];
    echo "Veritabanı: MySQL ($h/$dn)\n";
}
kopyala($root, $tmp);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", 'yonlendirici.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', $tmp . '/sunucu.log', 'w']], $pipes, $tmp);
usleep(800000);
$a = new Istemci();
$z = new Istemci(); // ziyaretçi

bolum('Kurulum ve giriş');
$r = $a->istek('GET', "$B/yonetim/");
$r = $a->istek('POST', "$B/yonetim/", ['_csrf' => $a->csrf($r['govde']), 'site_adi' => 'Test Sitesi', 'ad' => 'Yönetici', 'eposta' => 'admin@ornek.com', 'sifre' => 'cokgizlisifre1', 'ornek' => '1'] + $dbAyar);
k($r['kod'] === 302, 'Kurulum tamamlanır');
k(is_file("$tmp/veri/kurulum.kilit"), 'Kurulum kilidi oluşur');
$r = $a->istek('GET', "$B/yonetim/");
$T = $a->csrf($r['govde']);
k($r['kod'] === 200 && str_contains($r['govde'], 'Yayına hazırlık'), 'Panel ve yayına hazırlık listesi');
k(str_contains(implode(' ', $r['basliklar']['content-security-policy'] ?? []), "object-src 'none'"), 'Panelde CSP başlığı');

bolum('Tüm panel ekranları');
foreach (['panel', 'icerik&sablon=blog', 'duzenle&sablon=blog', 'duzenle&sablon=hizmetler', 'duzenle&sablon=sss', 'medya', 'formlar', 'genel', 'kullanicilar', 'ayarlar',
    'yedek', 'gunluk', 'sistem', 'profil', 'takvim', 'seo', 'yonlendirmeler', 'api', 'araclar', 'siparisler', 'icerik'] as $s) {
    $r = $a->istek('GET', "$B/yonetim/?s=$s");
    k($r['kod'] === 200 && temiz($r['govde']), "Panel: $s", (string) $r['kod']);
}

bolum('Ayarlar: çok dil, API, MCP, YZ');
$r = $a->istek('POST', "$B/yonetim/?s=ayarlar", ['_csrf' => $T, 'eylem' => 'kaydet', 'site_adi' => 'Test Sitesi', 'diller' => 'tr, en, XX', 'site_url' => $B,
    'api_acik' => '0', 'mcp_acik' => '1', 'yz_llms' => '1', 'yz_markdown' => '1', 'onbellek' => '1', 'cerez_bandi' => '1', 'yz_botlari' => 'sadece_arama',
    'google_analitik' => 'kotu<script>', 'isletme_adi' => 'Test Ltd', 'isletme_il' => 'Ankara', 'yz_url' => 'javascript:alert(1)']);
k($r['kod'] === 302, 'Ayarlar kaydedilir');

bolum('İçerik: kayıt, bloklar, slug değişince 301');
$r = $a->istek('POST', "$B/yonetim/?s=duzenle&sablon=hizmetler&dil=tr", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'Web Tasarım', 'alan[ozet_metin]' => 'Kurumsal siteler',
    'alan[fiyat]' => '15000,50', 'alan[govde][b1][_tip]' => 'metin', 'alan[govde][b1][icerik]' => '<h2>Süreç</h2><p>Merhaba <script>alert(1)</script></p>',
    'alan[govde][b2][_tip]' => 'cagri', 'alan[govde][b2][baslik]' => 'Teklif alın', 'alan[govde][b2][buton]' => 'Bize yazın', 'alan[govde][b2][link]' => '/iletisim',
    'alan[govde][b3][_tip]' => 'yok', 'seo[anahtar]' => 'web tasarım']);
k($r['kod'] === 302, 'Bloklu içerik kaydedilir');
preg_match('/id=(\d+)/', $r['basliklar']['location'][0] ?? '', $m);
$hid = (int) ($m[1] ?? 0);
$r = $z->istek('GET', "$B/hizmetler/web-tasarim");
k($r['kod'] === 200 && str_contains($r['govde'], 'id="surec"') && str_contains($r['govde'], 'class="cagri"') && !str_contains($r['govde'], 'alert(1)'), 'Bloklar render edilir, XSS temizlenir');
k(str_contains($r['govde'], '15.000,50 ₺'), 'Fiyat ₺ biçimi');
$r = $a->istek('POST', "$B/yonetim/?s=duzenle&id=$hid", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'Web Tasarım', 'slug' => 'kurumsal-web-tasarim', 'alan[ozet_metin]' => 'x']);
$r = $z->istek('GET', "$B/hizmetler/web-tasarim");
k($r['kod'] === 301 && str_ends_with($r['basliklar']['location'][0] ?? '', '/hizmetler/kurumsal-web-tasarim'), 'Adres değişince otomatik 301', (string) $r['kod']);

bolum('Çok dil');
$r = $a->istek('GET', "$B/yonetim/?s=duzenle&sablon=blog&dil=en&kaynak=2");
k($r['kod'] === 200 && str_contains($r['govde'], 'data-yz-cevir'), 'Çeviri ekranı açılır');
$r = $a->istek('POST', "$B/yonetim/?s=duzenle&sablon=blog&dil=en&kaynak=2", ['_csrf' => $T, 'eylem' => 'yayinla', 'baslik' => 'BOZKURT CMS is live!', 'alan[icerik]' => '<p>Hello world</p><h2>Features</h2>', 'alan[kategori]' => 'Duyuru']);
k($r['kod'] === 302, 'İngilizce çeviri kaydedilir');
$r = $z->istek('GET', "$B/en/blog/bozkurt-cms-is-live");
k($r['kod'] === 200 && str_contains($r['govde'], 'lang="en"') && str_contains($r['govde'], 'hreflang="tr"') && str_contains($r['govde'], 'Hello world'), '/en/ adresi, lang ve hreflang');
$r = $z->istek('GET', "$B/blog/bozkurt-cms-yayinda");
k(str_contains($r['govde'], 'hreflang="en"') && str_contains($r['govde'], 'class="diller"'), 'Türkçe sayfada hreflang ve dil seçici');
$r = $z->istek('GET', "$B/en");
k($r['kod'] === 200 && str_contains($r['govde'], 'Skip to content'), 'Şablon metin çevirisi (cevir süzgeci)');
$r = $z->istek('GET', "$B/xx");
k($r['kod'] === 404, 'Geçersiz dil kodu reddedilir', $r['kod'] . ' ' . substr($r['govde'], 0, 200));
$r = $z->istek('GET', "$B/sitemap.xml");
k(str_contains($r['govde'], 'xhtml:link rel="alternate" hreflang="en"'), 'Sitemap hreflang');

bolum('Yapay zekâ dostu çıktılar');
$r = $z->istek('GET', "$B/llms.txt");
k($r['kod'] === 200 && str_starts_with($r['govde'], '# Test Sitesi') && str_contains($r['govde'], '.md)'), 'llms.txt');
$r = $z->istek('GET', "$B/llms-full.txt");
k($r['kod'] === 200 && str_contains($r['govde'], 'Kaynak:'), 'llms-full.txt');
$r = $z->istek('GET', "$B/blog/bozkurt-cms-yayinda.md");
k($r['kod'] === 200 && str_starts_with($r['govde'], '# BOZKURT CMS yayında!') && str_contains($r['govde'], '## '), 'Sayfanın Markdown sürümü');
$r = $z->istek('GET', "$B/robots.txt");
k(str_contains($r['govde'], "User-agent: GPTBot") && !str_contains($r['govde'], 'User-agent: OAI-SearchBot'), 'robots.txt: eğitim botları engelli, arama botları serbest');
$r = $z->istek('GET', "$B/");
k(str_contains($r['govde'], '"@type":"LocalBusiness"') && !str_contains($r['govde'], 'kotu<script>'), 'LocalBusiness şeması; geçersiz GA kimliği kaydedilmez');
$r = $z->istek('GET', "$B/sss");
k($r['kod'] === 200, 'SSS sayfası');

bolum('API ve MCP');
$r = $z->istek('GET', "$B/api/v1/blog");
k($r['kod'] === 403, 'API kapalıyken anahtarsız okuma reddedilir');
$r = $a->istek('POST', "$B/yonetim/?s=api", ['_csrf' => $T, 'eylem' => 'olustur', 'ad' => 'Test', 'kapsam' => 'yaz']);
preg_match('/(bz_[a-f0-9]{48})/', $r['govde'], $m);
$tok = $m[1] ?? '';
k($tok !== '', 'Yazma anahtarı oluşturulur');
$auth = ["Authorization: Bearer $tok"];
$r = $z->istek('GET', "$B/api/v1/blog?limit=2", null, $auth);
$j = json_decode($r['govde'], true);
k($r['kod'] === 200 && count($j['veri'] ?? []) === 2 && str_starts_with($j['veri'][0]['url'] ?? '', '/'), 'Anahtarla okuma');
$r = $z->istek('POST', "$B/api/v1/blog", json_encode(['baslik' => 'API yazısı', 'durum' => 'yayinda', 'alanlar' => ['icerik' => '<p>API <img src=x onerror=alert(1)></p>', 'kategori' => 'Haber']]), [...$auth, 'Content-Type: application/json']);
$j = json_decode($r['govde'], true);
k($r['kod'] === 201 && ($j['veri']['slug'] ?? '') === 'api-yazisi' && !str_contains($r['govde'], 'onerror'), 'API ile içerik oluşturma (temizlenmiş)');
$r = $z->istek('PATCH', "$B/api/v1/blog/api-yazisi", json_encode(['alanlar' => ['kategori' => 'Rehber']]), [...$auth, 'Content-Type: application/json']);
$j = json_decode($r['govde'], true);
k($r['kod'] === 200 && ($j['veri']['kategori'] ?? '') === 'Rehber' && str_contains($j['veri']['icerik'] ?? '', 'API'), 'PATCH diğer alanları korur');
$r = $z->istek('DELETE', "$B/api/v1/blog/api-yazisi", null, ["Authorization: Bearer bz_" . str_repeat('0', 48)]);
k($r['kod'] === 401, 'Geçersiz anahtarla silme reddedilir');
$r = $z->istek('DELETE', "$B/api/v1/blog/api-yazisi", null, $auth);
k($r['kod'] === 200, 'Geçerli anahtarla silme');
$mcp = fn(array $p) => $z->istek('POST', "$B/mcp", json_encode($p), [...$auth, 'Content-Type: application/json', 'Accept: application/json, text/event-stream']);
$r = $mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']]]);
$j = json_decode($r['govde'], true);
k(($j['result']['serverInfo']['name'] ?? '') === 'bozkurt-cms', 'MCP initialize');
$r = $mcp(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
k($r['kod'] === 202, 'MCP bildirimi 202');
$r = $mcp(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
$j = json_decode($r['govde'], true);
k(in_array('taslak_olustur', array_column($j['result']['tools'] ?? [], 'name'), true), 'MCP tools/list (yazma araçları dahil)');
$r = $mcp(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'ara', 'arguments' => ['sorgu' => 'kvkk']]]);
$j = json_decode($r['govde'], true);
k(str_contains($j['result']['content'][0]['text'] ?? '', 'KVKK'), 'MCP arama aracı');
$r = $mcp(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'taslak_olustur', 'arguments' => ['sablon' => 'blog', 'baslik' => 'YZ taslağı', 'alanlar' => ['icerik' => '<p>Taslak</p>']]]]);
$j = json_decode($r['govde'], true);
k(str_contains($j['result']['content'][0]['text'] ?? '', '"ok": true'), 'MCP taslak oluşturur');
$r = $z->istek('GET', "$B/blog/yz-taslagi");
k($r['kod'] === 404, 'MCP taslağı yayında değil (insan onayı gerekir)');
$r = $z->istek('POST', "$B/mcp", '{"jsonrpc":"2.0","id":1,"method":"tools/list"}', ['Content-Type: application/json']);
k($r['kod'] === 401, 'MCP anahtarsız reddedilir');

bolum('Formlar, yönlendirmeler, 404');
$r = $z->istek('GET', "$B/iletisim");
preg_match('/name="_bz_t" value="([^"]+)"/', $r['govde'], $m);
sleep(3);
$r = $z->istek('POST', "$B/iletisim", ['_bz_form' => 'iletisim', '_bz_t' => $m[1] ?? '', 'ad_soyad' => '=HYPERLINK("http://kotu")', 'eposta' => 'a@b.com', 'mesaj' => 'Selam', 'kvkk_onay' => '1', 'iys_onay' => '1']);
k(str_contains($r['govde'], 'bz-form-basari') && str_contains($r['govde'], 'bz_form_gonderildi'), 'Form gönderimi + dönüşüm olayı');
$r = $a->istek('GET', "$B/yonetim/?s=formlar&csv=1");
k(str_contains($r['govde'], "'=HYPERLINK"), 'CSV formül enjeksiyonu etkisizleştirilir');
$a->istek('POST', "$B/yonetim/?s=yonlendirmeler", ['_csrf' => $T, 'eylem' => 'ekle', 'kaynak' => '/eski-sayfa.html', 'hedef' => '/hakkimizda', 'kod' => '301']);
$r = $z->istek('GET', "$B/eski-sayfa.html");
k($r['kod'] === 301 && str_ends_with($r['basliklar']['location'][0] ?? '', '/hakkimizda'), 'Elle eklenen 301 yönlendirme');
$a->istek('POST', "$B/yonetim/?s=yonlendirmeler", ['_csrf' => $T, 'eylem' => 'ekle', 'kaynak' => '/x', 'hedef' => 'javascript:alert(1)', 'kod' => '301']);
$r = $z->istek('GET', "$B/x");
k($r['kod'] === 404, 'javascript: hedefli yönlendirme reddedilir');
$z->istek('GET', "$B/bulunmayan-sayfa-123");
$z->istek('GET', "$B/bulunmayan-sayfa-123");
$r = $a->istek('GET', "$B/yonetim/?s=yonlendirmeler");
k(str_contains($r['govde'], 'bulunmayan-sayfa-123'), '404 günlüğü kaydı');

bolum('Satır içi düzenleme ve önizleme');
$r = $a->istek('GET', "$B/hakkimizda?duzenle=1");
k(str_contains($r['govde'], '<bz-duzenle') && str_contains($r['govde'], 'Düzenlemeyi bitir'), 'Satır içi düzenleme modu');
preg_match('/<bz-duzenle data-id="(\d+)" data-alan="baslik"/', $r['govde'], $m);
$r = $a->istek('POST', "$B/yonetim/?s=satir_ici", ['_csrf' => $T, 'id' => $m[1] ?? 0, 'alan' => 'baslik', 'deger' => 'Biz Kimiz?'], ['Accept: application/json']);
k(str_contains($r['govde'], '"ok": true'), 'Satır içi kayıt');
$r = $z->istek('GET', "$B/hakkimizda");
k(str_contains($r['govde'], 'Biz Kimiz?') && !str_contains($r['govde'], '<bz-duzenle'), 'Ziyaretçi düzenleme işaretlerini görmez');
$r = $a->istek('POST', "$B/yonetim/?s=satir_ici", ['_csrf' => 'yanlis', 'id' => 1, 'alan' => 'baslik', 'deger' => 'x'], ['Accept: application/json']);
k($r['kod'] === 419, 'Satır içi kayıt CSRF korumalı');

bolum('İçe / dışa aktarma');
$wxr = $tmp . '/wp.xml';
file_put_contents($wxr, '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wp="http://wordpress.org/export/1.2/" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"><channel>'
    . '<item><title>WP Yazısı &xxe;</title><link>https://eski.com/2020/wp-yazisi/</link><content:encoded><![CDATA[Birinci paragraf' . "\n\n" . 'İkinci]]></content:encoded><category domain="category">Haber</category><wp:post_name>wp-yazisi</wp:post_name><wp:post_date>2020-01-02 10:00:00</wp:post_date><wp:status>publish</wp:status><wp:post_type>post</wp:post_type></item>'
    . '<item><title>Ek</title><wp:post_type>attachment</wp:post_type><wp:status>inherit</wp:status></item></channel></rss>');
$r = $a->istek('POST', "$B/yonetim/?s=araclar", ['_csrf' => $T, 'eylem' => 'wp', 'sablon' => 'blog', 'icerik_alani' => 'icerik', 'kategori_alani' => 'kategori', 'turler' => 'post', 'yonlendir' => '1', 'wxr' => new CURLFile($wxr, 'text/xml', 'wp.xml')]);
k(str_contains($r['govde'], '1 içerik eklendi'), 'WordPress içe aktarma');
$r = $z->istek('GET', "$B/blog/wp-yazisi");
k($r['kod'] === 200 && !str_contains($r['govde'], 'root:'), 'İçe aktarılan yazı yayında, XXE yok');
$r = $z->istek('GET', "$B/2020/wp-yazisi");
k($r['kod'] === 301, 'Eski WordPress adresinden 301');
$r = $a->istek('GET', "$B/yonetim/?s=araclar&statik=1");
k($r['kod'] === 200 && str_starts_with($r['govde'], 'PK'), 'Statik site ZIP');

bolum('SEO kanonik adresler');
$r = $z->istek('GET', "$B/Blog/");
k($r['kod'] === 301 && str_ends_with($r['basliklar']['location'][0] ?? '', '/blog'), 'Büyük harf + sondaki eğik çizgi → 301 kanonik');
$r = $z->istek('GET', "$B/index.php");
k($r['kod'] === 301, '/index.php → / (yinelenen ana sayfa önlenir)');
$r = $z->istek('GET', "$B/ara?q=kvkk");
k(str_contains($r['govde'], 'noindex'), 'Arama sonuç sayfası noindex');
$r = $z->istek('GET', "$B/yonetim/?s=icerik", null, []);

bolum('Güvenlik regresyonları');
$r = $z->istek('GET', "$B/veri/yapilandirma.php");
k($r['kod'] === 403, 'veri/ erişimi kapalı');
$r = $z->istek('GET', "$B/yonetim/?s=ayarlar");
k($r['kod'] === 302, 'Panel girişsiz yönlendirir');
$r = $z->istek('GET', "$B/blog?x=" . bin2hex(random_bytes(8)));
k(!isset($r['basliklar']['x-bozkurt-onbellek']), 'Bilinmeyen sorgu parametresi önbelleğe yazılmaz');
$r = $z->istek('GET', "$B/", null, ['Host: kotu.example']);
k(!str_contains($r['govde'], 'kotu.example'), 'Host başlığı zehirlenmesi canonical\'a sızmaz');
$r = $z->istek('POST', "$B/yonetim/?s=sifremi_unuttum", ['_csrf' => 'x']);
$c = new Istemci();
$r = $c->istek('GET', "$B/yonetim/?s=sifremi_unuttum");
$r = $c->istek('POST', "$B/yonetim/?s=sifremi_unuttum", ['_csrf' => $c->csrf($r['govde']), 'eposta' => 'yok@ornek.com']);
$r2 = $c->istek('GET', "$B/yonetim/?s=sifremi_unuttum");
$r2 = $c->istek('POST', "$B/yonetim/?s=sifremi_unuttum", ['_csrf' => $c->csrf($r2['govde']), 'eposta' => 'admin@ornek.com']);
k(str_contains($r['govde'], 'kayıtlıysa') && str_contains($r2['govde'], 'kayıtlıysa'), 'Şifre sıfırlama kullanıcı varlığını sızdırmaz');
$r = $z->istek('GET', "$B/yonetim/?s=sifre_yenile&t=" . str_repeat('a', 64));
k(str_contains($r['govde'], 'geçersiz'), 'Sahte sıfırlama belirteci reddedilir');
$evil = $tmp . '/kotu.json';
file_put_contents($evil, json_encode(['tablolar' => ['bz_ayarlar' => [['anahtar' => 'x', 'deger' => 'y', 'deger) VALUES (1,1); DROP TABLE bz_icerik; --' => 'z']]]]));
$r = $a->istek('POST', "$B/yonetim/?s=yedek", ['_csrf' => $T, 'eylem' => 'geri_yukle', 'yedek' => new CURLFile($evil, 'application/json', 'kotu.json')]);
$r = $z->istek('GET', "$B/blog/bozkurt-cms-yayinda");
k($r['kod'] === 200, 'Kötü niyetli yedek dosyası SQL enjeksiyonu yapamaz');
$php = $tmp . '/kabuk.php.jpg';
file_put_contents($php, "\xFF\xD8\xFF\xE0<?php system(\$_GET['c']); ?>");
$r = $a->istek('POST', "$B/yonetim/?s=medya&json=1", ['_csrf' => $T, 'eylem' => 'yukle', 'dosya' => new CURLFile($php, 'image/jpeg', 'kabuk.php.jpg')], ['Accept: application/json']);
k(str_contains($r['govde'], '"ok": false'), 'PHP içeren sahte resim reddedilir');
$r = $z->istek('POST', "$B/odeme/baslat", ['icerik' => $hid, 'ad_soyad' => 'a']);
k($r['kod'] === 404, 'Ödeme kapalıyken uç nokta kapalı');
$r = $z->istek('POST', "$B/odeme/paytr-bildirim", ['merchant_oid' => 'x', 'status' => 'success', 'total_amount' => '1', 'hash' => 'sahte']);
k($r['kod'] !== 200 || !str_contains($r['govde'], 'OK') , 'Sahte PayTR bildirimi kabul edilmez');

bolum('Kötü niyetli saldırgan senaryoları');
$r = $z->istek('GET', "$B/ara?q=" . rawurlencode('"><script>alert(1)</script>'));
k(!str_contains($r['govde'], '<script>alert(1)'), 'Yansıyan XSS (arama) yok');
$r = $z->istek('GET', "$B/blog?kategori=" . rawurlencode("' OR 1=1 --") . '&sayfa=' . rawurlencode('1 UNION SELECT 1'));
k($r['kod'] === 200 && temiz($r['govde']), 'SQL enjeksiyonu denemesi zararsız');
$r = $z->istek('GET', "$B/api/v1/blog?sirala=" . rawurlencode('id;DROP TABLE bz_icerik') . '&filtre=' . rawurlencode("x=1' OR '1'='1"), null, $auth);
k($r['kod'] === 200 && temiz($r['govde']), 'API parametre enjeksiyonu zararsız');
foreach (['/..%2f..%2fveri/yapilandirma.php', '/index.php?yol=../../etc/passwd', '/sablonlar/../veri/yapilandirma.php', '/_bakim', '/404', '/parcalar/ust', '/blog/..%2f..%2fveri.md', '/bozkurt/src/App.php', '/yuklemeler/.htaccess'] as $u) {
    $r = $z->istek('GET', $B . $u);
    k(in_array($r['kod'], [301, 403, 404], true) && !str_contains($r['govde'], "'anahtar'") && !str_contains($r['govde'], 'root:'), "Yol geçişi engellenir: $u", (string) $r['kod']);
}
$r = $a->istek('GET', "$B/yonetim/?s=" . rawurlencode('../../veri'));
k($r['kod'] === 200 && temiz($r['govde']), 'Panel bölüm parametresi temizlenir');
$big = ['_bz_form' => 'iletisim', '_bz_t' => '1.x'];
for ($i = 0; $i < 300; $i++) {
    $big["alan$i"] = str_repeat('A', 100);
}
$r = $z->istek('POST', "$B/iletisim", $big);
k($r['kod'] === 200 && temiz($r['govde']), 'Aşırı alanlı form (sahte belirteç) sessizce reddedilir');
$saldirgan = new Istemci();
for ($i = 0; $i < 6; $i++) {
    $g = $saldirgan->istek('GET', "$B/yonetim/?s=giris");
    $r = $saldirgan->istek('POST', "$B/yonetim/?s=giris", ['_csrf' => $saldirgan->csrf($g['govde']), 'eposta' => 'admin@ornek.com', 'sifre' => 'yanlis' . $i]);
}
k(str_contains($r['govde'], 'Çok fazla başarısız deneme'), 'Kaba kuvvet girişinde kilit');
$r = $saldirgan->istek('POST', "$B/yonetim/?s=giris", ['_csrf' => $saldirgan->csrf($g['govde']), 'eposta' => 'admin@ornek.com', 'sifre' => 'cokgizlisifre1'], ['X-Forwarded-For: 1.2.3.4', 'CF-Connecting-IP: 5.6.7.8']);
k(str_contains($r['govde'], 'Çok fazla başarısız deneme'), 'Sahte vekil başlığıyla kilit atlatılamaz');
foreach (glob("$tmp/veri/*.sqlite") as $dbf) {
    (new PDO('sqlite:' . $dbf))->exec('DELETE FROM bz_giris_denemeleri'); // sonraki testler için kilidi kaldır
}
if (isset($pdo)) {
    $pdo->exec('DELETE FROM bz_giris_denemeleri');
}
$c = new Istemci();
$r = $c->istek('GET', "$B/yonetim/?s=giris");
$sc = strtolower(implode(' ', $r['basliklar']['set-cookie'] ?? []));
k(str_contains($sc, 'httponly') && str_contains($sc, 'samesite=lax'), 'Oturum çerezi HttpOnly + SameSite');
$r = $z->istek('GET', "$B/yonetim/?s=api");
k($r['kod'] === 302, 'Ziyaretçi API anahtar sayfasına erişemez');
$e = new Istemci();
$g = $a->istek('GET', "$B/yonetim/?s=kullanicilar");
$a->istek('POST', "$B/yonetim/?s=kullanicilar", ['_csrf' => $T, 'eylem' => 'kaydet', 'ad' => 'Yazar', 'eposta' => 'yazar@ornek.com', 'rol' => 'yazar', 'sifre' => 'yazarsifresi1']);
$g = $e->istek('GET', "$B/yonetim/?s=giris");
$e->istek('POST', "$B/yonetim/?s=giris", ['_csrf' => $e->csrf($g['govde']), 'eposta' => 'yazar@ornek.com', 'sifre' => 'yazarsifresi1']);
$g = $e->istek('GET', "$B/yonetim/");
$ET = $e->csrf($g['govde']);
$r = $e->istek('GET', "$B/yonetim/?s=ayarlar");
k($r['kod'] === 403, 'Yazar ayarlara erişemez (yetki yükseltme yok)');
$r = $e->istek('POST', "$B/yonetim/?s=duzenle&sablon=ana-sayfa", ['_csrf' => $ET, 'eylem' => 'yayinla', 'alan[baslik]' => 'HACK']);
$r = $z->istek('GET', "$B/");
k(!str_contains($r['govde'], 'HACK'), 'Yazar başkasının sayfasını değiştiremez');
$r = $e->istek('POST', "$B/yonetim/?s=surum", ['_csrf' => $ET, 'id' => 1]);
$r = $e->istek('POST', "$B/yonetim/?s=medya", ['_csrf' => $ET, 'eylem' => 'sil', 'id' => 1]);
$r = $e->istek('POST', "$B/yonetim/?s=duzenle&sablon=blog", ['_csrf' => $ET, 'eylem' => 'yayinla', 'baslik' => 'Yazar yazısı', 'alan[icerik]' => '<p>x</p>']);
$r = $z->istek('GET', "$B/blog/yazar-yazisi");
k($r['kod'] === 404, 'Yazar doğrudan yayınlayamaz (taslak olarak kaydedilir)');

bolum('Sunucu günlüğü');
$log = (string) @file_get_contents("$tmp/veri/hatalar.log");
k(!preg_match('/PHP (Fatal|Warning|Deprecated|Notice)/', $log), 'PHP hata/uyarı yok', mb_substr($log, 0, 600));

proc_terminate($proc);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$pass geçti, $fail başarısız.\n";
exit($fail ? 1 : 0);
