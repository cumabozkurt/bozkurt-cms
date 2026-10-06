<?php
use Bozkurt\App;
use Bozkurt\Str;

$baslik = 'Siparişler';
$durumlar = ['bekliyor' => 'Bekliyor', 'odendi' => 'Ödendi', 'basarisiz' => 'Başarısız'];
?>
<div class="sayfa-ust">
  <div><h1>Siparişler</h1><p class="soluk">Toplam tahsilat: <strong><?= e(Str::tl($toplam)) ?></strong></p></div>
  <a class="dugme hayalet" href="<?= e(App::adminUrl('siparisler', ['csv' => 1])) ?>"><?= bz_ikon('yedek', 16) ?> e-Fatura için CSV</a>
</div>
<?php if (!$siparisler): ?>
  <div class="kart bos-durum"><p class="soluk">Henüz sipariş yok. Ürün sayfalarına <code>&lt;bz:odeme alan="fiyat" /&gt;</code> ekleyin.</p></div>
<?php else: ?>
<section class="kart tablo-kart">
  <table class="tablo">
    <thead><tr><th>Sipariş</th><th>Müşteri</th><th>Ürün</th><th>Tutar</th><th>Durum</th><th>Tarih</th></tr></thead>
    <tbody>
    <?php foreach ($siparisler as $s): $m = json_decode((string) $s['musteri'], true) ?: []; ?>
      <tr>
        <td><strong><?= e($s['siparis_no']) ?></strong><small class="soluk"><?= e($s['saglayici']) ?></small></td>
        <td><?= e($m['ad_soyad'] ?? '') ?><small class="soluk"><?= e($m['eposta'] ?? '') ?> · <?= e($m['telefon'] ?? '') ?></small></td>
        <td><?= e($s['urun']) ?></td>
        <td><?= e(Str::tl($s['tutar'])) ?></td>
        <td><span class="etiket <?= $s['durum'] === 'odendi' ? 'yayinda' : ($s['durum'] === 'bekliyor' ? 'taslak' : 'yeni') ?>"><?= e($durumlar[$s['durum']] ?? $s['durum']) ?></span></td>
        <td><?= Str::date($s['tarih'], 'd M Y H:i') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
