<?php
/**
 * GitHub depo adını tüm dosyalarda ayarlar (rozetler, bağlantılar, tek tıkla güncelleme).
 * Kullanım:  php bin/depo-ayarla.php kullaniciadi/bozkurt-cms
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$yeni = $argv[1] ?? '';
if (!preg_match('#^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})/[A-Za-z0-9_.\-]{1,100}$#', $yeni)) {
    fwrite(STDERR, "Kullanım: php bin/depo-ayarla.php kullaniciadi/depo-adi\n");
    exit(1);
}
$kok = dirname(__DIR__);
$boot = (string) file_get_contents("$kok/bozkurt/boot.php");
if (!preg_match("/const BZ_REPO = '([^']+)';/", $boot, $m)) {
    fwrite(STDERR, "bozkurt/boot.php içinde BZ_REPO bulunamadı.\n");
    exit(1);
}
$eski = $m[1];
if ($eski === $yeni) {
    echo "Depo zaten $yeni.\n";
    exit(0);
}
$dosyalar = ['bozkurt/boot.php', 'testler/phpstan-bootstrap.php', 'README.md', 'composer.json', 'index.php', 'SECURITY.md', 'CONTRIBUTING.md',
    '.github/ISSUE_TEMPLATE/config.yml', 'docs/HOSTINGER-KURULUM.md'];
$n = 0;
foreach ($dosyalar as $d) {
    $yol = "$kok/$d";
    if (!is_file($yol)) {
        continue;
    }
    $icerik = (string) file_get_contents($yol);
    $guncel = str_replace($eski, $yeni, $icerik);
    if ($guncel !== $icerik) {
        file_put_contents($yol, $guncel);
        echo "  ✓ $d\n";
        $n++;
    }
}
echo "$n dosya güncellendi: $eski → $yeni\n";
