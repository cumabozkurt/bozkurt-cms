<?php
use Bozkurt\App;

$baslik = 'Araçlar';
$coklu = array_filter($templates, fn($t) => $t['meta']['coklu']);
?>
<div class="sayfa-ust"><div><h1>Araçlar</h1></div></div>
<div class="izgara-2">
  <section class="kart">
    <h2>Güncelleme</h2>
    <p>Kurulu sürüm: <strong><?= BZ_VERSION ?></strong></p>
    <?php if ($kontrol): ?>
      <?php if (!$surum): ?>
        <div class="bildirim uyari">Sürüm bilgisine ulaşılamadı. Güncelleme deposu veri/yapilandirma.php içindeki 'guncelleme_deposu' anahtarıyla değiştirilebilir.</div>
      <?php elseif (version_compare($surum['surum'], BZ_VERSION, '>')): ?>
        <div class="bildirim basari">Yeni sürüm var: <strong><?= e($surum['surum']) ?></strong></div>
        <pre class="kod"><?= e(mb_substr($surum['notlar'], 0, 1500)) ?></pre>
        <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="guncelle"><button class="dugme" data-onay="Güncelleme öncesi otomatik yedek alınır. veri/, sablonlar/, tema/ ve yuklemeler/ klasörlerine dokunulmaz. Devam edilsin mi?">Şimdi güncelle</button></form>
      <?php else: ?>
        <div class="bildirim basari">En güncel sürümü kullanıyorsunuz.</div>
      <?php endif; ?>
    <?php else: ?>
      <a class="dugme hayalet" href="<?= e(App::adminUrl('araclar', ['kontrol' => 1])) ?>">Güncellemeleri denetle</a>
    <?php endif; ?>
    <p class="soluk kucuk-metin">Paketler SHA-256 ile doğrulanır; yalnızca GitHub sürümlerinden indirilir.</p>
  </section>
  <section class="kart">
    <h2>Statik site dışa aktar</h2>
    <p class="soluk">Tüm sayfaları (tüm dillerde) HTML olarak ZIP'leyin; Netlify, Cloudflare Pages veya GitHub Pages'te barındırın. Formlar ve arama statik sürümde çalışmaz.</p>
    <a class="dugme hayalet" href="<?= e(App::adminUrl('araclar', ['statik' => 1])) ?>"><?= bz_ikon('yedek', 16) ?> Statik ZIP indir</a>
  </section>
</div>
<form class="kart" method="post" enctype="multipart/form-data">
  <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="wp">
  <h2>WordPress'ten içe aktar</h2>
  <p class="soluk">WordPress › Araçlar › Dışa aktar ile aldığınız XML dosyasını yükleyin. Yazılar seçtiğiniz şablona taşınır; isterseniz eski adreslerden 301 yönlendirme oluşturulur.</p>
  <?php if ($sonuc): ?>
    <div class="bildirim <?= $sonuc['hatalar'] ? 'uyari' : 'basari' ?>"><?= (int) $sonuc['eklenen'] ?> içerik eklendi, <?= (int) $sonuc['atlanan'] ?> atlandı.<?php foreach (array_slice($sonuc['hatalar'], 0, 10) as $h): ?><br><?= e($h) ?><?php endforeach; ?></div>
  <?php endif; ?>
  <div class="alanlar">
    <div class="alan yarim"><label>WXR dosyası</label><input type="file" name="wxr" accept=".xml" required></div>
    <div class="alan yarim"><label>Hedef şablon</label><select name="sablon"><?php foreach ($coklu as $n => $t): ?><option value="<?= e($n) ?>"><?= e($t['meta']['baslik']) ?></option><?php endforeach; ?></select></div>
    <div class="alan yarim"><label>İçerik alanı</label><input name="icerik_alani" value="icerik"></div>
    <div class="alan yarim"><label>Kategori alanı</label><input name="kategori_alani" value="kategori"></div>
    <div class="alan yarim"><label>Özet alanı (isteğe bağlı)</label><input name="ozet_alani" value=""></div>
    <div class="alan yarim"><label>Yazı türleri</label><input name="turler" value="post"><small class="yardim">Sayfalar için: post,page</small></div>
  </div>
  <label class="anahtar"><input type="checkbox" name="yonlendir" value="1" checked><span></span> Eski WordPress adreslerinden 301 yönlendirme oluştur</label>
  <button class="dugme">İçe aktar</button>
</form>
