<?php
use Bozkurt\App;
use Bozkurt\Str;

$baslik = 'Yönlendirmeler';
?>
<div class="sayfa-ust"><div><h1>Yönlendirmeler ve 404 günlüğü</h1><p class="soluk">Eski adresleri yenilerine 301 ile yönlendirerek arama motoru sıralamanızı koruyun. İçerik adresi değiştiğinde yönlendirme otomatik eklenir.</p></div></div>
<div class="duzen-izgara">
  <div class="duzen-ana">
    <section class="kart tablo-kart">
      <table class="tablo">
        <thead><tr><th>Eski adres</th><th>Hedef</th><th>Kod</th><th>Kullanım</th><th class="dar"></th></tr></thead>
        <tbody>
        <?php if (!$liste): ?><tr><td colspan="5" class="soluk">Henüz yönlendirme yok.</td></tr><?php endif; ?>
        <?php foreach ($liste as $r): ?>
          <tr><td><code>/<?= e($r['kaynak']) ?></code></td><td><code><?= e($r['hedef']) ?></code></td><td><?= (int) $r['kod'] ?></td><td><?= (int) $r['sayac'] ?></td>
            <td><form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="sil"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="baglanti tehlike-metin" data-onay="Yönlendirme silinsin mi?">Sil</button></form></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <section class="kart">
      <div class="sayfa-ust"><h2>404 günlüğü — en çok istenen bulunamayan adresler</h2>
        <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="404_temizle"><button class="dugme kucuk hayalet" data-onay="404 günlüğü temizlensin mi?">Temizle</button></form></div>
      <?php if (!$hatalar): ?><p class="soluk">Kayıt yok.</p><?php endif; ?>
      <ul class="liste">
        <?php foreach ($hatalar as $h): ?>
          <li><code>/<?= e($h['yol']) ?></code> <span class="etiket"><?= (int) $h['sayac'] ?>×</span> <button type="button" class="baglanti" data-yonlendir="/<?= e($h['yol']) ?>">Yönlendir</button>
            <small class="soluk"><?= Str::date($h['son'], 'once') ?><?= $h['yonlendiren'] ? ' · kaynak: ' . e($h['yonlendiren']) : '' ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
  <aside class="duzen-yan">
    <form method="post" class="kart" id="yonlendirme-formu">
      <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="ekle">
      <h3>Yeni yönlendirme</h3>
      <div class="alan"><label>Eski adres</label><input name="kaynak" placeholder="/eski-sayfa.html" required></div>
      <div class="alan"><label>Yeni adres</label><input name="hedef" placeholder="/yeni-sayfa veya https://…"></div>
      <div class="alan"><label>Tür</label><select name="kod"><option value="301">301 Kalıcı</option><option value="302">302 Geçici</option><option value="410">410 Kaldırıldı</option></select></div>
      <button class="dugme tam">Ekle</button>
    </form>
    <form method="post" class="kart">
      <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="ice_aktar">
      <h3>Toplu içe aktar</h3>
      <div class="alan"><textarea name="liste" rows="6" placeholder="/eski-1 /yeni-1&#10;/eski-2 https://site.com/yeni 302"></textarea><small class="yardim">Her satıra: eski yeni [kod]</small></div>
      <button class="dugme hayalet tam">İçe aktar</button>
    </form>
  </aside>
</div>
