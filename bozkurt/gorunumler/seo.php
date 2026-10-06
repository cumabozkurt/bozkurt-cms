<?php
use Bozkurt\App;

$baslik = 'SEO Denetimi';
?>
<div class="sayfa-ust"><div><h1>SEO Denetimi</h1><p class="soluk">Yayındaki tüm içerikler tarandı: başlık uzunluğu, meta açıklama, yinelenen başlık, alt metin ve kırık iç bağlantılar.</p></div></div>
<div class="istatistikler">
  <div class="istatistik <?= $sorunlar ? 'vurgulu' : '' ?>"><span>İçerik sorunu</span><strong><?= count($sorunlar) ?></strong></div>
  <div class="istatistik <?= $kirik ? 'vurgulu' : '' ?>"><span>Kırık iç bağlantı</span><strong><?= count($kirik) ?></strong></div>
  <a class="istatistik <?= $altsiz ? 'vurgulu' : '' ?>" href="<?= e(App::adminUrl('medya', ['tur' => 'altsiz'])) ?>"><span>Alt metinsiz görsel</span><strong><?= $altsiz ?></strong></a>
  <a class="istatistik" href="<?= e(App::adminUrl('yonlendirmeler')) ?>"><span>404 günlüğü</span><strong>→</strong></a>
</div>
<div class="izgara-2">
  <section class="kart">
    <h2>İçerik sorunları</h2>
    <?php if (!$sorunlar): ?><p class="soluk">Harika, sorun bulunamadı. 🎉</p><?php endif; ?>
    <ul class="liste">
      <?php foreach (array_slice($sorunlar, 0, 200) as [$mesaj, $r, $url]): ?>
        <li><a href="<?= e($url) ?>"><?= e($r['baslik']) ?></a> <span class="etiket"><?= e(strtoupper($r['dil'])) ?></span><small class="soluk"><?= e($mesaj) ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="kart">
    <h2>Kırık iç bağlantılar</h2>
    <?php if (!$kirik): ?><p class="soluk">Kırık bağlantı yok.</p><?php endif; ?>
    <ul class="liste">
      <?php foreach ($kirik as [$link, $rows]): ?>
        <li><code><?= e($link) ?></code><small class="soluk">Geçtiği yer: <?php foreach (array_slice($rows, 0, 3) as $r): ?><a href="<?= e(App::adminUrl('duzenle', ['id' => $r['id']])) ?>"><?= e($r['baslik']) ?></a> <?php endforeach; ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<section class="kart">
  <h2>Yapay zekâ görünürlüğü</h2>
  <ul class="liste">
    <li><a href="<?= e(App::$basePath) ?>llms.txt" target="_blank">llms.txt</a><small class="soluk">Yapay zekâ asistanları için site özeti <?= App::setting('yz_llms', '1') === '1' ? '— açık' : '— kapalı' ?></small></li>
    <li><a href="<?= e(App::$basePath) ?>robots.txt" target="_blank">robots.txt</a><small class="soluk">YZ bot politikası: <?= e(['izin' => 'tüm botlara izin', 'sadece_arama' => 'yalnızca YZ arama botları (eğitim botları engelli)', 'engelle' => 'tüm YZ botları engelli'][App::setting('yz_botlari', 'sadece_arama')] ?? '') ?></small></li>
    <li><a href="<?= e(App::$basePath) ?>sitemap.xml" target="_blank">sitemap.xml</a><small class="soluk">hreflang ve görsel bilgisiyle</small></li>
  </ul>
</section>
