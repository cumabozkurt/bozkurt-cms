<?php
use Bozkurt\App;

$baslik = 'İçerik';
?>
<div class="sayfa-ust"><h1>İçerik</h1></div>
<?php if (!$templates): ?>
  <div class="kart bos-durum">
    <h2>Henüz şablon yok</h2>
    <p><code>sablonlar/</code> klasörüne bir <code>.html</code> dosyası ekleyin ve düzenlenebilir yerleri <code>&lt;bz:alan&gt;</code> ile işaretleyin. Panel onu otomatik olarak tanır.</p>
  </div>
<?php endif; ?>
<div class="kartlar-izgara">
  <?php foreach ($templates as $tn => $t): ?>
    <a class="kart sablon-kart" href="<?= e(App::adminUrl($t['meta']['coklu'] ? 'icerik' : 'duzenle', ['sablon' => $tn])) ?>">
      <?= bz_ikon($t['meta']['ikon'] ?? 'sayfa', 26) ?>
      <strong><?= e($t['meta']['baslik']) ?></strong>
      <small class="soluk"><?= $t['meta']['coklu'] ? 'Çoklu içerik' : 'Tekil sayfa' ?> · <?= count($t['alanlar']) ?> alan · <?= e($tn) ?>.html</small>
      <?php foreach (\Bozkurt\Template::lint($tn) as $sorun): ?><small class="hata-metni">⚠ <?= e($sorun) ?></small><?php endforeach; ?>
    </a>
  <?php endforeach; ?>
</div>
