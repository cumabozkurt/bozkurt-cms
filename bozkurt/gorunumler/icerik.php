<?php
use Bozkurt\App;
use Bozkurt\Auth;
use Bozkurt\Content;
use Bozkurt\Str;

$baslik = $sablon['meta']['baslik'];
?>
<div class="sayfa-ust">
  <div><h1><?= e($baslik) ?></h1><p class="soluk"><?= Str::number($toplam) ?> içerik</p></div>
  <a class="dugme" href="<?= e(App::adminUrl('duzenle', ['sablon' => $ad, 'dil' => $dil])) ?>"><?= bz_ikon('arti', 16) ?> Yeni ekle</a>
</div>
<?php if (App::isMultilingual()): ?>
<nav class="sekmeler">
  <?php foreach (App::languages() as $l): ?><a class="<?= $l === $dil ? 'aktif' : '' ?>" href="<?= e(App::adminUrl('icerik', ['sablon' => $ad, 'dil' => $l])) ?>"><?= e(App::langName($l)) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>

<form class="filtre-cubugu" method="get">
  <input type="hidden" name="s" value="icerik"><input type="hidden" name="sablon" value="<?= e($ad) ?>"><input type="hidden" name="dil" value="<?= e($dil) ?>">
  <div class="arama-kutu"><?= bz_ikon('arama', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Başlıkta ara…"></div>
  <select name="durum" onchange="this.form.submit()">
    <option value="">Tüm durumlar</option>
    <option value="yayinda" <?= $durum === 'yayinda' ? 'selected' : '' ?>>Yayında</option>
    <option value="taslak" <?= $durum === 'taslak' ? 'selected' : '' ?>>Taslak</option>
  </select>
</form>

<?php if (!$satirlar): ?>
  <div class="kart bos-durum">
    <h2><?= $q !== '' ? 'Sonuç bulunamadı' : 'İlk içeriğinizi ekleyin' ?></h2>
    <p class="soluk"><?= $q !== '' ? 'Farklı bir arama deneyin.' : 'Eklediğiniz içerikler sitede otomatik olarak listelenir.' ?></p>
    <a class="dugme" href="<?= e(App::adminUrl('duzenle', ['sablon' => $ad, 'dil' => $dil])) ?>">Yeni ekle</a>
  </div>
<?php else: ?>
<form method="post" class="kart tablo-kart">
  <?= bz_csrf_field() ?>
  <?php if (Auth::can('icerik_tum')): ?>
  <div class="toplu">
    <select name="toplu"><option value="">Toplu işlem…</option><option value="yayinla">Yayınla</option><option value="taslak">Taslağa al</option><option value="sil">Sil</option></select>
    <button class="dugme kucuk hayalet" data-onay="Seçili içeriklere işlem uygulansın mı?">Uygula</button>
  </div>
  <?php endif; ?>
  <table class="tablo">
    <thead><tr><th class="dar"><input type="checkbox" data-hepsini-sec></th><th>Başlık</th><th>Durum</th><?php if (App::isMultilingual()): ?><th>Çeviriler</th><?php endif; ?><th>Yayın tarihi</th><th class="dar"></th></tr></thead>
    <tbody>
      <?php foreach ($satirlar as $r): ?>
        <tr>
          <td><input type="checkbox" name="secili[]" value="<?= $r['id'] ?>"></td>
          <td><a class="satir-baslik" href="<?= e(App::adminUrl('duzenle', ['id' => $r['id']])) ?>"><?= e($r['baslik']) ?></a><small class="soluk">/<?= e($ad . '/' . $r['slug']) ?></small></td>
          <td><span class="etiket <?= $r['durum'] ?>"><?= $r['durum'] === 'yayinda' ? (strtotime($r['yayin_tarihi']) > time() ? 'Zamanlandı' : 'Yayında') : 'Taslak' ?></span></td>
          <?php if (App::isMultilingual()): $var = $ceviriler[(int) $r['grup'] ?: (int) $r['id']] ?? []; ?>
          <td class="ceviri-durum"><?php foreach (App::languages() as $l): if ($l === $dil) continue; ?><a class="etiket <?= in_array($l, $var, true) ? 'yayinda' : '' ?>" href="<?= e(in_array($l, $var, true) ? App::adminUrl('duzenle', ['sablon' => $ad, 'dil' => $l, 'kaynak' => $r['id']]) : App::adminUrl('duzenle', ['sablon' => $ad, 'dil' => $l, 'kaynak' => $r['id']])) ?>" title="<?= in_array($l, $var, true) ? 'Çeviriyi düzenle' : 'Çeviri ekle' ?>"><?= strtoupper(e($l)) ?><?= in_array($l, $var, true) ? ' ✓' : ' +' ?></a> <?php endforeach; ?></td>
          <?php endif; ?>
          <td><?= Str::date($r['yayin_tarihi'], 'd M Y H:i') ?></td>
          <td><a class="ikon-dugme" href="<?= e(Content::url($r)) ?><?= $r['durum'] !== 'yayinda' ? '?onizleme=1' : '' ?>" target="_blank" title="Görüntüle"><?= bz_ikon('goz', 16) ?></a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</form>
<?php if ($sayfa_sayisi > 1): ?>
  <nav class="sayfalama">
    <?php for ($i = 1; $i <= $sayfa_sayisi; $i++): ?>
      <a class="<?= $i === $sayfa ? 'aktif' : '' ?>" href="<?= e(App::adminUrl('icerik', ['sablon' => $ad, 'sayfa' => $i, 'q' => $q, 'durum' => $durum, 'dil' => $dil])) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
