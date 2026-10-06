<?php
use Bozkurt\App;
use Bozkurt\Str;
use Bozkurt\Validate;

$baslik = 'İçerik Takvimi';
$ts = strtotime($ay . '-01');
$gunSayisi = (int) date('t', $ts);
$ilkGun = ((int) date('N', $ts)) - 1;
$onceki = date('Y-m', strtotime('-1 month', $ts));
$sonraki = date('Y-m', strtotime('+1 month', $ts));
?>
<div class="sayfa-ust">
  <div><h1><?= e(Str::AYLAR[(int) date('n', $ts)] . ' ' . date('Y', $ts)) ?></h1><p class="soluk">Yayınlanan ve zamanlanmış içerikler. Resmî tatiller işaretlidir.</p></div>
  <div class="dugme-grubu">
    <a class="dugme hayalet" href="<?= e(App::adminUrl('takvim', ['ay' => $onceki])) ?>">‹ Önceki</a>
    <a class="dugme hayalet" href="<?= e(App::adminUrl('takvim')) ?>">Bugün</a>
    <a class="dugme hayalet" href="<?= e(App::adminUrl('takvim', ['ay' => $sonraki])) ?>">Sonraki ›</a>
  </div>
</div>
<div class="takvim kart">
  <?php foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $g): ?><div class="takvim-baslik"><?= $g ?></div><?php endforeach; ?>
  <?php for ($i = 0; $i < $ilkGun; $i++): ?><div class="takvim-gun bos"></div><?php endfor; ?>
  <?php for ($d = 1; $d <= $gunSayisi; $d++): $tarih = sprintf('%s-%02d', $ay, $d); $tatil = Validate::holiday($tarih); ?>
    <div class="takvim-gun <?= $tarih === date('Y-m-d') ? 'bugun' : '' ?> <?= $tatil ? 'tatil' : '' ?>">
      <span class="gun-no"><?= $d ?></span>
      <?php if ($tatil): ?><small class="tatil-adi"><?= e($tatil) ?></small><?php endif; ?>
      <?php foreach ($gunler[$d] ?? [] as $r): ?>
        <a class="takvim-oge <?= strtotime($r['yayin_tarihi']) > time() && $r['durum'] === 'yayinda' ? 'zamanli' : e($r['durum']) ?>" href="<?= e(App::adminUrl('duzenle', ['id' => $r['id']])) ?>" title="<?= e($r['baslik']) ?>">
          <?= e(substr($r['yayin_tarihi'], 11, 5)) ?> <?= e(Str::excerpt($r['baslik'], 28)) ?><?= count(App::languages()) > 1 ? ' · ' . strtoupper(e($r['dil'])) : '' ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>
