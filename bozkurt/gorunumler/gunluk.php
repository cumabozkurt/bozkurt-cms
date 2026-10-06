<?php
use Bozkurt\Str;

$baslik = 'Etkinlik Günlüğü';
$adlar = ['giris' => 'Giriş yaptı', 'cikis' => 'Çıkış yaptı', 'icerik_ekle' => 'İçerik ekledi', 'icerik_guncelle' => 'İçerik güncelledi',
    'icerik_sil' => 'İçerik sildi', 'medya_yukle' => 'Medya yükledi', 'medya_sil' => 'Medya sildi', 'ayarlar_guncelle' => 'Ayarları değiştirdi',
    'genel_guncelle' => 'Genel alanları değiştirdi', 'yedek_indir' => 'Yedek indirdi', 'yedek_geri_yukle' => 'Yedek geri yükledi',
    'surum_geri_yukle' => 'Sürüm geri yükledi', 'sifre_degistir' => 'Şifresini değiştirdi', '2fa_ac' => '2FA açtı', '2fa_kapat' => '2FA kapattı',
    'kullanici_kaydet' => 'Kullanıcı kaydetti', 'kullanici_sil' => 'Kullanıcı sildi', 'form_sil' => 'Form kaydı sildi'];
?>
<div class="sayfa-ust"><div><h1>Etkinlik Günlüğü</h1><p class="soluk">Panelde yapılan son 300 işlem.</p></div></div>
<section class="kart tablo-kart">
  <table class="tablo">
    <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>İşlem</th><th>Ayrıntı</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($kayitlar as $k): ?>
      <tr><td title="<?= e($k['tarih']) ?>"><?= Str::date($k['tarih'], 'd M H:i') ?></td><td><?= e($k['ad'] ?? '—') ?></td>
        <td><?= e($adlar[$k['islem']] ?? $k['islem']) ?></td><td class="soluk"><?= e($k['detay']) ?></td><td class="soluk"><?= e($k['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
