<?php
/**
 * BOZKURT CMS duman testi: geçici bir kopyada kurulum yapar ve temel sayfaları dolaşır.
 * Kullanım: php testler/duman.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/bozkurt-duman-' . bin2hex(random_bytes(4));
$port = random_int(20000, 40000);
$base = "http://127.0.0.1:$port";
$fail = 0;

function kopyala(string $src, string $dst): void
{
    @mkdir($dst, 0755, true);
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..' || $f === '.git') {
            continue;
        }
        is_dir("$src/$f") ? kopyala("$src/$f", "$dst/$f") : copy("$src/$f", "$dst/$f");
    }
}

function istek(string $url, array $post = [], ?string &$cookie = null): array
{
    $ctx = ['http' => ['ignore_errors' => true, 'follow_location' => 0, 'header' => $cookie ? "Cookie: $cookie\r\n" : '']];
    if ($post) {
        $ctx['http']['method'] = 'POST';
        $ctx['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $ctx['http']['content'] = http_build_query($post);
    }
    $body = @file_get_contents($url, false, stream_context_create($ctx));
    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
            $code = (int) $m[1];
        }
        if (preg_match('/^Set-Cookie:\s*(BZOTURUM=[^;]+)/i', $h, $m)) {
            $cookie = $m[1];
        }
    }
    return [$code, (string) $body];
}

function kontrol(bool $ok, string $msg): void
{
    global $fail;
    echo ($ok ? "  ✓ " : "  ✗ ") . $msg . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
}

kopyala($root, $tmp);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", 'yonlendirici.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $tmp);
usleep(700000);

echo "BOZKURT CMS duman testi\n";
$cookie = null;
[$c, $html] = istek("$base/yonetim/", [], $cookie);
kontrol($c === 200 && str_contains($html, 'Kurulumu tamamla'), 'Kurulum ekranı açılıyor');
preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m);
[$c] = istek("$base/yonetim/", ['_csrf' => $m[1] ?? '', 'site_adi' => 'Test', 'ad' => 'Test Kullanıcı', 'eposta' => 'test@ornek.com', 'sifre' => 'cokgizlisifre1', 'surucu' => 'sqlite', 'ornek' => '1'], $cookie);
kontrol($c === 302, 'Kurulum tamamlanıyor');
[$c, $html] = istek("$base/yonetim/", [], $cookie);
kontrol($c === 200 && str_contains($html, 'Panel'), 'Panel açılıyor');

foreach (['/' => 200, '/blog' => 200, '/blog/bozkurt-cms-yayinda' => 200, '/hakkimizda' => 200, '/iletisim' => 200,
    '/kvkk' => 200, '/ara?q=kvkk' => 200, '/olmayan-sayfa' => 404, '/sitemap.xml' => 200, '/robots.txt' => 200, '/rss.xml' => 200,
    '/veri/yapilandirma.php' => 403, '/sablonlar/blog.html' => 403] as $path => $want) {
    [$c, $body] = istek($base . $path);
    kontrol($c === $want && !preg_match('/Fatal error|Warning:|Deprecated:/', $body), "$path → $want");
}
foreach (['icerik&sablon=blog', 'duzenle&sablon=blog', 'medya', 'formlar', 'genel', 'kullanicilar', 'ayarlar', 'yedek', 'gunluk', 'sistem', 'profil'] as $s) {
    [$c, $body] = istek("$base/yonetim/?s=$s", [], $cookie);
    kontrol($c === 200 && !preg_match('/Fatal error|Warning:|Deprecated:/', $body), "Panel: $s");
}

proc_terminate($proc);
exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail kontrol başarısız.\n" : "\nTüm kontroller geçti. 🐺\n";
exit($fail ? 1 : 0);
