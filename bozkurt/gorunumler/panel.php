<?php
use Bozkurt\App;
use Bozkurt\Auth;
use Bozkurt\Str;

$baslik = 'Panel';
$saat = (int) date('G');
$selam = $saat < 6 ? 'İyi geceler' : ($saat < 12 ? 'Günaydın' : ($saat < 18 ? 'İyi günler' : 'İyi akşamlar'));
?>
<section class="karsilama">
  <div>
    <h1><?= $selam ?>, <?= e(explode(' ', $user['ad'])[0]) ?> 👋</h1>
    <p class="soluk"><?= Str::date(bz_now(), 'd F Y, l') ?> · Bugün neyi güncelliyoruz?</p>
  </div>
  <div class="hizli">
    <?php foreach ($templates as $tn => $t): if (!$t['meta']['coklu']) continue; ?>
      <a class="dugme" href="<?= e(App::adminUrl('duzenle', ['sablon' => $tn])) ?>"><?= bz_ikon('arti', 16) ?> <?= __('Yeni') ?> <?= e(Str::lower($t['meta']['tekil_adi'] ?? $t['meta']['baslik'])) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="istatistikler">
  <?php foreach ($sayilar as $tn => $n): ?>
    <a class="istatistik" href="<?= e(App::adminUrl('icerik', ['sablon' => $tn])) ?>"><span><?= e($templates[$tn]['meta']['baslik']) ?></span><strong><?= Str::number($n) ?></strong></a>
  <?php endforeach; ?>
  <a class="istatistik" href="<?= e(App::adminUrl('medya')) ?>"><span>Medya</span><strong><?= Str::number($medya_sayisi) ?></strong></a>
  <?php if (Auth::can('formlar')): ?>
  <a class="istatistik <?= $okunmamis ? 'vurgulu' : '' ?>" href="<?= e(App::adminUrl('formlar')) ?>"><span>Okunmamış mesaj</span><strong><?= Str::number($okunmamis) ?></strong></a>
  <?php endif; ?>
  <div class="istatistik"><span>Taslak</span><strong><?= Str::number($taslak_sayisi) ?></strong></div>
</section>

<?php $eksik = array_filter($kontroller, fn($k) => !$k[1]); if ($eksik): ?>
<section class="kart baslangic">
  <h2>Yayına hazırlık <small class="soluk"><?= count($kontroller) - count($eksik) ?>/<?= count($kontroller) ?></small></h2>
  <div class="ilerleme"><span style="width:<?= (int) round((count($kontroller) - count($eksik)) / max(1, count($kontroller)) * 100) ?>%"></span></div>
  <ul class="kontrol-listesi">
    <?php foreach ($kontroller as [$m, $ok, $url]): ?><li class="<?= $ok ? 'tamam' : '' ?>"><?= $ok ? '✅' : '⬜' ?> <?= $ok ? e($m) : '<a href="' . e($url) . '">' . e($m) . '</a>' ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php if ($hata_404): ?><div class="bildirim uyari"><?= $hata_404 ?> adres tekrar tekrar 404 veriyor. <a href="<?= e(App::adminUrl('yonlendirmeler')) ?>">Yönlendirme ekleyin</a>.</div><?php endif; ?>

<div class="izgara-2">
  <section class="kart">
    <h2>Son düzenlenenler</h2>
    <?php if (!$son_icerik): ?><p class="soluk">Henüz içerik yok.</p><?php endif; ?>
    <ul class="liste">
      <?php foreach ($son_icerik as $r): ?>
        <li><a href="<?= e(App::adminUrl('duzenle', ['id' => $r['id']])) ?>"><?= e($r['baslik']) ?></a>
          <span class="etiket <?= $r['durum'] ?>"><?= $r['durum'] === 'yayinda' ? 'Yayında' : 'Taslak' ?></span>
          <small class="soluk"><?= e($templates[$r['sablon']]['meta']['baslik'] ?? $r['sablon']) ?> · <?= Str::date($r['guncelleme'], 'once') ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="kart">
    <h2>Son mesajlar</h2>
    <?php if (!$son_formlar): ?><p class="soluk">Henüz form mesajı yok.</p><?php endif; ?>
    <ul class="liste">
      <?php foreach ($son_formlar as $f): $d = json_decode((string) $f['veri'], true) ?: []; ?>
        <li><a href="<?= e(App::adminUrl('formlar', ['id' => $f['id']])) ?>"><?= e($d['ad_soyad'] ?? $d['ad'] ?? $d['eposta'] ?? ('#' . $f['id'])) ?></a>
          <?php if (!$f['okundu']): ?><span class="etiket yeni">Yeni</span><?php endif; ?>
          <small class="soluk"><?= e(Str::excerpt($d['mesaj'] ?? implode(' ', $d), 60)) ?> · <?= Str::date($f['tarih'], 'once') ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php if ($zamanlanan): ?>
<section class="kart" style="margin-top:18px">
  <h2>Zamanlanmış yayınlar</h2>
  <ul class="liste kompakt"><?php foreach ($zamanlanan as $z): ?><li><a href="<?= e(App::adminUrl('duzenle', ['id' => $z['id']])) ?>"><?= e($z['baslik']) ?></a><small class="soluk"><?= Str::date($z['yayin_tarihi'], 'd F Y H:i') ?></small></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if (App::setting('bakim_modu') === '1'): ?>
  <div class="bildirim uyari">Bakım modu açık: ziyaretçiler siteyi göremiyor. <a href="<?= e(App::adminUrl('ayarlar')) ?>">Ayarlardan kapatın</a>.</div>
<?php endif; ?>
