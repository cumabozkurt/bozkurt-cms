<?php
use Bozkurt\App;
use Bozkurt\Str;

$baslik = 'Sistem Durumu';
$disk = @disk_free_space(BZ_ROOT);
$upl = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BZ_UPLOADS, FilesystemIterator::SKIP_DOTS)) as $f) {
    $upl += $f->getSize();
}
?>
<div class="sayfa-ust"><div><h1>Sistem Durumu</h1></div></div>
<div class="izgara-2">
  <section class="kart">
    <h2>Gereksinimler</h2>
    <table class="tablo kompakt">
      <?php foreach ($kontroller as [$ad, $ok, $deger, $zorunlu]): ?>
        <tr><td><?= e($ad) ?></td><td><?= e($deger) ?></td><td><?= $ok ? '✅' : ($zorunlu ? '❌' : '➖') ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>
  <section class="kart">
    <h2>Kurulum</h2>
    <dl class="tanimlar">
      <dt>BOZKURT sürümü</dt><dd><?= BZ_VERSION ?></dd>
      <dt>Veritabanı</dt><dd><?= e(App::db()->driver === 'mysql' ? 'MySQL ' . App::db()->pdo->getAttribute(PDO::ATTR_SERVER_VERSION) : 'SQLite ' . App::db()->pdo->getAttribute(PDO::ATTR_SERVER_VERSION)) ?></dd>
      <dt>Sunucu</dt><dd><?= e($_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI) ?></dd>
      <dt>Güzel URL</dt><dd><?= !empty(App::$config['guzel_url']) ? 'Açık' : 'Kapalı (index.php?yol=)' ?></dd>
      <dt>Saat dilimi</dt><dd><?= e(date_default_timezone_get()) ?> · <?= Str::date(bz_now(), 'd F Y H:i') ?></dd>
      <dt>Yüklemeler</dt><dd><?= Str::bytes($upl) ?></dd>
      <dt>Boş disk</dt><dd><?= $disk ? Str::bytes((int) $disk) : '—' ?></dd>
      <dt>OPcache</dt><dd><?= function_exists('opcache_get_status') && @opcache_get_status(false) ? 'Açık' : 'Kapalı' ?></dd>
    </dl>
  </section>
</div>
