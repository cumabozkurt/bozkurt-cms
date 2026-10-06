<?php
use Bozkurt\App;

$baslik = 'Yedekleme';
?>
<div class="sayfa-ust"><div><h1>Yedekleme</h1><p class="soluk">Yedekler veritabanından bağımsızdır: SQLite’tan MySQL’e (veya tersi) taşımak için de kullanabilirsiniz.</p></div></div>
<div class="izgara-2">
  <section class="kart">
    <h2>Yedek indir</h2>
    <p class="soluk">İçerikler, genel alanlar, ayarlar, medya kayıtları, form mesajları ve kullanıcılar.</p>
    <div class="dugme-grubu">
      <a class="dugme" href="<?= e(App::adminUrl('yedek', ['indir' => 1, 'dosyalar' => 1])) ?>"><?= bz_ikon('yedek', 16) ?> Tam yedek (ZIP)</a>
      <a class="dugme hayalet" href="<?= e(App::adminUrl('yedek', ['indir' => 1])) ?>">Yalnızca veri (JSON)</a>
    </div>
    <p class="soluk kucuk-metin">İpucu: Hostinger’ın otomatik yedeklerine ek olarak ayda bir tam yedeği bilgisayarınıza indirin.</p>
  </section>
  <form class="kart" method="post" enctype="multipart/form-data">
    <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="geri_yukle">
    <h2>Yedekten geri yükle</h2>
    <p class="soluk">Mevcut veriler, yedektekilerle <strong>değiştirilir</strong>.</p>
    <div class="alan"><input type="file" name="yedek" accept=".zip,.json" required></div>
    <button class="dugme tehlike" data-onay="Mevcut tüm veriler yedektekilerle değiştirilecek. Emin misiniz?">Geri yükle</button>
  </form>
</div>
