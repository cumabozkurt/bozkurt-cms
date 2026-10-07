<?php
/**
 * BOZKURT CMS birim testleri: veritabanı ve sunucu gerektirmeyen saf mantık
 * (HTML temizleyici, Markdown, şablon derleyici/denetleyici, doğrulayıcılar, TOTP, metin araçları).
 * Geçici bir kopyada, kurulum yapılmadan çalışır.
 * Kullanım: php testler/birim.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/bozkurt-birim-' . bin2hex(random_bytes(4));
$fail = 0;
$pass = 0;

function kopyala(string $src, string $dst): void
{
    @mkdir($dst, 0755, true);
    foreach (scandir($src) as $f) {
        if (in_array($f, ['.', '..', '.git', 'docs', 'node_modules', 'vendor'], true)) {
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

// Kurulumsuz temiz kopya: geliştiricinin yerel veri/ klasörü testleri etkilemez
kopyala($root, $tmp);
foreach (array_merge(glob("$tmp/veri/*.sqlite*") ?: [], ["$tmp/veri/yapilandirma.php", "$tmp/veri/kurulum.kilit"]) as $f) {
    @unlink($f);
}
register_shutdown_function(static fn() => exec('rm -rf ' . escapeshellarg($tmp)));

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $tmp . '/bozkurt/boot.php';

use Bozkurt\App;
use Bozkurt\Forms;
use Bozkurt\Markdown;
use Bozkurt\Redirects;
use Bozkurt\Runtime;
use Bozkurt\Sanitizer;
use Bozkurt\Str;
use Bozkurt\Template;
use Bozkurt\Totp;
use Bozkurt\Validate;

k(!App::installed(), 'Kurulumsuz ortamda çalışıyor');

bolum('HTML temizleyici (XSS)');
$c = Sanitizer::clean('<p onclick="x()">Merhaba <script>alert(1)</script><b>dünya</b></p>');
k($c === '<p>Merhaba <b>dünya</b></p>', 'script ve olay öznitelikleri atılır', $c);
k(!str_contains(Sanitizer::clean('<img src=x onerror="alert(1)">'), 'onerror'), 'onerror kaldırılır');
foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', " javascript:alert(1)", "java\tscript:alert(1)", "java\nscript:alert(1)", "\x01javascript:alert(1)", 'data:text/html,x', 'vbscript:x'] as $u) {
    $out = Sanitizer::clean('<a href="' . htmlspecialchars($u) . '">x</a>');
    k(!preg_match('/href="[^"]*script|href="data:/i', $out), 'Tehlikeli bağlantı engellenir: ' . json_encode($u), $out);
}
k(str_contains(Sanitizer::clean('<a href="https://ornek.com/a?b=1">x</a>'), 'href="https://ornek.com/a?b=1"'), 'https bağlantısı korunur');
k(str_contains(Sanitizer::clean('<a href="/blog/yazi">x</a>'), 'href="/blog/yazi"'), 'Göreli bağlantı korunur');
k(str_contains(Sanitizer::clean('<a href="https://x.com" target="_blank">x</a>'), 'rel="noopener noreferrer"'), 'target=_blank için rel=noopener eklenir');
k(str_contains(Sanitizer::clean('<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'), '<iframe'), 'İzinli iframe korunur');
k(!str_contains(Sanitizer::clean('<iframe src="https://kotu.example/x"></iframe>'), '<iframe'), 'İzinsiz iframe kaldırılır');
k(Sanitizer::clean('<style>p{}</style><!-- yorum --><p>a</p>') === '<p>a</p>', 'style ve yorumlar kaldırılır');
k(Sanitizer::clean('<section><p>a</p></section>') === '<p>a</p>', 'Bilinmeyen etiket kaldırılır, içerik korunur');

bolum('Markdown');
$h = Markdown::toHtml("# Başlık\n\nBir **kalın** ve *eğik* [bağlantı](https://ornek.com).\n\n- a\n- b\n\n```\n<script>x</script>\n```");
k(str_contains($h, '<h2>Başlık</h2>'), '# başlık h2 olur (sayfada tek h1 kalır)');
k(str_contains($h, '<strong>kalın</strong>') && str_contains($h, '<em>eğik</em>'), 'Kalın ve eğik');
k(str_contains($h, '<a href="https://ornek.com">bağlantı</a>'), 'Bağlantı');
k(str_contains($h, '<ul><li>a</li><li>b</li></ul>'), 'Liste');
k(str_contains($h, '&lt;script&gt;') && !str_contains($h, '<script>'), 'Kod bloğu kaçışlanır');
k(!str_contains(Markdown::toHtml('[x](javascript:alert(1))'), 'javascript:'), 'Markdown javascript: bağlantısı temizlenir');
k(!str_contains(Markdown::toHtml('<img src=x onerror=alert(1)>'), '<img'), 'Markdown içindeki ham HTML kaçışlanır');
$md = Markdown::fromHtml('<h2>Bölüm</h2><p>Metin <strong>önemli</strong> <a href="https://a.com">bağ</a></p><ul><li>x</li></ul>');
k(str_contains($md, '## Bölüm') && str_contains($md, '**önemli**') && str_contains($md, '[bağ](https://a.com)') && str_contains($md, '- x'), 'HTML → Markdown');

bolum('Şablon derleyici');
$t = new Template();
$php = $t->compile('<p><?php system("id"); ?></p><?= 1 ?>');
k(!preg_match('/<\?php system|<\?=\s*1/', $php), 'Şablondaki PHP kodu çalıştırılamaz');
k(str_contains((new Template())->compile('<bz:liste sablon="blog">x<bz:degilse/>y</bz:liste>'), 'yalnızca <bz:eger>'), '<bz:degilse> <bz:eger> dışında derlenmiş PHP\'yi bozmaz');
$ok = true;
foreach (['<bz:liste sablon="blog">x<bz:degilse/>y</bz:liste>', '<bz:degilse/>', '<bz:yoksa-eger kosul="a"/>', '<bz:eger kosul="a">1<bz:yoksa-eger kosul="b"/>2<bz:degilse/>3</bz:eger>'] as $src) {
    $code = (new Template())->compile($src);
    $f = tempnam(sys_get_temp_dir(), 'bzt');
    file_put_contents($f, $code);
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
    @unlink($f);
    $ok = $ok && $rc === 0;
}
k($ok, 'Hatalı iç içe etiketlerde bile derlenmiş PHP geçerli');
[$path, $filters] = Template::parseExpr('oge.tarih | tarih:"d F Y" | varsayilan:"—"');
k($path === 'oge.tarih' && $filters === [['tarih', ['d F Y']], ['varsayilan', ['—']]], 'Süzgeç ifadesi ayrıştırılır');
[$path, $filters] = Template::parseExpr('a | birlestir:" | "');
k($path === 'a' && $filters === [['birlestir', [' | ']]], 'Tırnak içindeki | ayırıcı sayılmaz');
$a = Template::attrs('ad="baslik" etiket="Ba&amp;şlık" TUR="metin"');
k($a === ['ad' => 'baslik', 'etiket' => 'Ba&şlık', 'tur' => 'metin'], 'Öznitelikler ayrıştırılır');
try {
    Template::path('../veri/yapilandirma');
    k(false, 'Şablon yolunda dizin geçişi engellenir');
} catch (\RuntimeException) {
    k(true, 'Şablon yolunda dizin geçişi engellenir');
}

bolum('Şablon denetleyici (lint)');
$dir = BZ_TEMPLATES;
file_put_contents("$dir/_birim-bozuk.html", '<bz:liste sablon="blog"><bz:degilse/></bz:liste><bz:eger kosul="a"><bz:alan ad="Kötü Ad" tur="yok"/><bz:bilinmeyen/>');
$issues = implode("\n", Template::lint('_birim-bozuk'));
@unlink("$dir/_birim-bozuk.html");
k(str_contains($issues, 'yalnızca <bz:eger>'), 'Yanlış yerde <bz:degilse> bildirilir');
k(str_contains($issues, 'Kapatılmamış etiket: <bz:eger>'), 'Kapatılmamış etiket bildirilir');
k(str_contains($issues, 'geçersiz ad'), 'Geçersiz alan adı bildirilir');
k(str_contains($issues, 'Bilinmeyen alan türü'), 'Bilinmeyen alan türü bildirilir');
k(str_contains($issues, 'Bilinmeyen etiket: <bz:bilinmeyen>'), 'Bilinmeyen etiket bildirilir');
$bundled = [];
foreach (glob("$dir/*.html") ?: [] as $f) {
    foreach (Template::lint(basename($f, '.html')) as $i) {
        $bundled[] = basename($f) . ': ' . $i;
    }
}
k(!$bundled, 'Paketle gelen tüm şablonlar denetimden temiz geçer', implode('; ', $bundled));
$s = Template::scan('blog');
k($s['meta']['coklu'] === true && isset($s['alanlar']['icerik']), 'blog şablonu taranır (çoklu, alanlar)');
foreach (['README.md', 'README.tr.md'] as $readme) {
    // README'deki "60 saniyede şablon" örneği gerçekten çalışmalı
    $md = is_file("$root/$readme") ? (string) file_get_contents("$root/$readme") : '';
    if (!preg_match('/```html\n(<bz:sablon .*?)```/s', $md, $m)) {
        k(false, "$readme şablon örneği bulundu");
        continue;
    }
    file_put_contents("$dir/_birim-readme.html", $m[1]);
    $issues = Template::lint('_birim-readme');
    $f = tempnam(sys_get_temp_dir(), 'bzt');
    file_put_contents($f, (new Template())->compile($m[1]));
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
    @unlink($f);
    @unlink("$dir/_birim-readme.html");
    k(!$issues && $rc === 0, "$readme şablon örneği denetimden geçer ve geçerli PHP'ye derlenir", implode('; ', $issues));
}

bolum('Çalışma zamanı');
$R = new Runtime(['sayfa' => ['baslik' => 'Merhaba', 'fiyat' => '1234.5', 'etiketler' => ['a', 'b']], 'site' => ['ad' => 'Site']]);
k($R->out('baslik', [], true) === 'Merhaba', '{{ baslik }} = {{ sayfa.baslik }}');
k($R->out('fiyat', [['tl', []]], true) === '1.234,50 ₺', 'tl süzgeci');
k($R->out('baslik', [['buyuk', []]], true) === 'MERHABA', 'buyuk süzgeci');
k($R->out('yok', [['varsayilan', ['—']]], true) === '—', 'varsayilan süzgeci');
k($R->out('etiketler', [['birlestir', [' / ']]], true) === 'a / b', 'birlestir süzgeci');
$R2 = new Runtime(['sayfa' => ['baslik' => '<b>x</b>']]);
k($R2->out('baslik', [], true) === '&lt;b&gt;x&lt;/b&gt;' && $R2->out('baslik', [], false) === '<b>x</b>', '{{ }} kaçışlar, {{{ }}} ham yazar');
k($R->kosul('fiyat > 1000 ve baslik == "Merhaba"') && !$R->kosul('fiyat < 10') && $R->kosul('yok veya baslik') && $R->kosul('degil yok') && $R->kosul('etiketler icerir "a"'), 'Koşul ifadeleri');
$onay = $R->tag_kvkk_onay([]);
k(preg_match('#<a href="[^"]*kvkk" target="_blank" rel="noopener">Aydınlatma Metni</a>#', $onay) === 1, 'KVKK onay kutusunda bağlantı doğru oluşur', $onay);
$onay = $R->tag_kvkk_onay(['metin' => '{link}Metni{/link} okudum <b>']);
k(str_contains($onay, '>Metni</a> okudum &lt;b&gt;'), 'Özel KVKK metni kaçışlanır ve bağlantı korunur', $onay);
$ld = Runtime::jsonLd(['headline' => '</script><script>alert(1)</script>']);
k(!str_contains($ld, '</script>') && json_decode($ld, true)['headline'] === '</script><script>alert(1)</script>', 'JSON-LD içinde </script> kaçışlanır, veri bozulmaz');
k(str_contains(Runtime::videoEmbed('https://youtu.be/dQw4w9WgXcQ'), 'youtube-nocookie.com/embed/dQw4w9WgXcQ') && Runtime::videoEmbed('https://kotu.example/v') === '', 'Video gömme yalnızca YouTube/Vimeo');

bolum('Türkiye doğrulayıcıları');
k(Validate::tckn('10000000146') && !Validate::tckn('10000000147') && !Validate::tckn('01234567890'), 'T.C. kimlik no');
k(Validate::vkn('1234567890') && Validate::vkn('0010058960') && !Validate::vkn('0010058963') && !Validate::vkn('12345'), 'Vergi kimlik no (GİB algoritması)');
k(Validate::iban('TR330006100519786457841326') && Validate::iban('TR33 0006 1005 1978 6457 8413 26') && !Validate::iban('TR330006100519786457841327') && !Validate::iban('TR3300061005'), 'IBAN (mod 97)');
k(Validate::holiday('2026-10-29') === 'Cumhuriyet Bayramı' && Validate::holiday('2026-10-28') === null, 'Resmî tatil');
k(count(Validate::ILLER) === 81, '81 il');

bolum('TOTP (RFC 6238)');
$sec = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
k(Totp::code($sec, 59) === '287082' && Totp::code($sec, 1111111109) === '081804', 'RFC 6238 test vektörleri');
$step = Totp::verifyStep($sec, Totp::code($sec));
k($step !== null && abs($step - intdiv(time(), 30)) <= 1, 'Güncel kod doğrulanır ve zaman adımı döner');
k(Totp::verifyStep($sec, '000000') === null || Totp::code($sec) === '000000', 'Yanlış kod reddedilir');
k(strlen(Totp::secret()) === 32 && preg_match('/^[A-Z2-7]+$/', Totp::secret()) === 1, 'Gizli anahtar base32');

bolum('Metin araçları');
k(Str::slug("Çağrı Şöleni İstanbul'da!") === 'cagri-soleni-istanbulda', 'Türkçe slug');
k(Str::lower('IŞIK İZMİR') === 'ışık izmir' && Str::upper('ılık iğde') === 'ILIK İĞDE', 'İ/ı farkında büyük-küçük harf');
k(Str::title('ışık istanbul') === 'Işık İstanbul', 'Başlık biçimi');
k(Str::tl(1234.5) === '1.234,50 ₺' && Str::tl('99,9', false) === '99,90', 'TL biçimi');
k(Str::phone('+90 (532) 123 45 67') === '0532 123 45 67', 'Telefon biçimi');
k(Str::date('2026-10-29 10:00:00', 'd F Y l') === '29 Ekim 2026 Perşembe', 'Türkçe tarih');
k(Str::excerpt('<p>Bir iki üç dört beş</p>', 9) === 'Bir iki…', 'Özet kelime sınırında kesilir');

bolum('Yardımcılar');
k(e('<a href="x">') === '&lt;a href=&quot;x&quot;&gt;', 'e() HTML kaçışı');
k(bz_csv_cell('=HYPERLINK("x")') === "'=HYPERLINK(\"x\")" && bz_csv_cell('+1') === "'+1" && bz_csv_cell('Ali') === 'Ali', 'CSV formül enjeksiyonu engellenir');
k(!bz_public_url('http://127.0.0.1/') && !bz_public_url('http://10.0.0.5/x') && !bz_public_url('http://169.254.169.254/') && !bz_public_url('file:///etc/passwd') && !bz_public_url('gopher://8.8.8.8/'), 'SSRF: özel/ayrılmış adresler ve yabancı şemalar reddedilir');
k(bz_public_url('https://8.8.8.8/'), 'SSRF: genel IP kabul edilir');
k(Redirects::normalize('/Eski-Sayfa/?a=1') === 'eski-sayfa' && Redirects::normalize('https://eski.com/2020/01/yazi/') === '2020/01/yazi', 'Yönlendirme kaynağı normalleştirilir');
// Kurulumda kaydedilen site adresi standart dışı portu korumalı (Debian nginx: HTTP_HOST $host → port yok)
k(App::originFrom(['HTTP_HOST' => '127.0.0.1', 'SERVER_PORT' => '8073']) === 'http://127.0.0.1:8073', 'Köken: Host başlığında olmayan standart dışı port SERVER_PORT\'tan eklenir');
k(App::originFrom(['HTTP_HOST' => 'ornek.com:8080', 'SERVER_PORT' => '80']) === 'http://ornek.com:8080', 'Köken: Host başlığındaki port korunur');
k(App::originFrom(['HTTP_HOST' => 'ornek.com', 'SERVER_PORT' => '80']) === 'http://ornek.com'
    && App::originFrom(['HTTP_HOST' => 'ornek.com', 'SERVER_PORT' => '443', 'HTTPS' => 'on']) === 'https://ornek.com', 'Köken: standart portlar eklenmez');
k(App::originFrom(['HTTP_HOST' => 'ornek.com', 'SERVER_PORT' => '8443', 'HTTPS' => 'on']) === 'https://ornek.com:8443', 'Köken: HTTPS\'te standart dışı port eklenir');
k(App::originFrom(['HTTP_HOST' => 'ornek.com', 'SERVER_PORT' => '8080', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']) === 'http://ornek.com', 'Köken: ters vekil arkasında iç port eklenmez');
k(App::originFrom(['HTTP_HOST' => '[::1]', 'SERVER_PORT' => '8080']) === 'http://[::1]:8080'
    && App::originFrom(['HTTP_HOST' => '[::1]:9000', 'SERVER_PORT' => '8080']) === 'http://[::1]:9000', 'Köken: IPv6 adreslerinde port doğru işlenir');
k(!preg_match('/["<>]/', App::originFrom(['HTTP_HOST' => 'kötü"<host>', 'SERVER_PORT' => '80'])), 'Köken: Host başlığındaki zararlı karakterler atılır');
App::$config['anahtar'] = 'birim-test';
$old = (string) (time() - 10);
$tok = $old . '.' . substr(hash_hmac('sha256', $old, 'birim-test'), 0, 20);
k(Forms::tokenValid($tok) && !Forms::tokenValid(Forms::token()) && !Forms::tokenValid($old . '.0000') && !Forms::tokenValid('abc'), 'Form belirteci: imza ve en az 3 sn kuralı');

echo "\n$pass geçti, $fail başarısız.\n";
exit($fail ? 1 : 0);
