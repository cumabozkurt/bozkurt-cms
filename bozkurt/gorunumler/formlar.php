<?php
use Bozkurt\App;
use Bozkurt\Str;

$baslik = 'Form Mesajları';
?>
<div class="sayfa-ust">
  <div><h1>Form Mesajları</h1><p class="soluk">Sitedeki <code>&lt;bz:form&gt;</code> formlarından gelen gönderimler.</p></div>
  <a class="dugme hayalet" href="<?= e(App::adminUrl('formlar', array_filter(['form' => $form, 'csv' => 1]))) ?>"><?= bz_ikon('yedek', 16) ?> Excel'e aktar (CSV)</a>
</div>
<div class="sekmeler">
  <a class="<?= $form === '' ? 'aktif' : '' ?>" href="<?= e(App::adminUrl('formlar')) ?>">Tümü</a>
  <?php foreach ($formlar as $f): ?>
    <a class="<?= $form === $f['form'] ? 'aktif' : '' ?>" href="<?= e(App::adminUrl('formlar', ['form' => $f['form']])) ?>"><?= e($f['form']) ?> <em><?= (int) $f['adet'] ?></em><?= $f['yeni'] ? '<b class="nokta"></b>' : '' ?></a>
  <?php endforeach; ?>
</div>

<?php if ($goster): $d = json_decode((string) $goster['veri'], true) ?: []; ?>
  <section class="kart mesaj-detay">
    <div class="mesaj-ust"><h2><?= e($d['ad_soyad'] ?? $d['ad'] ?? 'Gönderim #' . $goster['id']) ?></h2>
      <small class="soluk"><?= e($goster['form']) ?> · <?= Str::date($goster['tarih'], 'd F Y H:i') ?> · IP <?= e($goster['ip']) ?> · KVKK onayı: <?= $goster['kvkk_onay'] ? '✅' : '—' ?></small></div>
    <dl class="tanimlar">
      <?php foreach ($d as $k => $v): ?><dt><?= e(Str::title(str_replace('_', ' ', $k))) ?></dt><dd><?= nl2br(e($v)) ?></dd><?php endforeach; ?>
    </dl>
    <div class="dugme-grubu">
      <?php if (!empty($d['eposta']) && filter_var($d['eposta'], FILTER_VALIDATE_EMAIL)): ?><a class="dugme" href="mailto:<?= e($d['eposta']) ?>?subject=<?= rawurlencode('Re: ' . App::setting('site_adi', '')) ?>">Yanıtla</a><?php endif; ?>
      <?php if (!empty($d['telefon'])): ?><a class="dugme hayalet" href="https://wa.me/90<?= e(substr(preg_replace('/\D/', '', $d['telefon']), -10)) ?>" target="_blank">WhatsApp</a><?php endif; ?>
      <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="sil"><input type="hidden" name="id" value="<?= $goster['id'] ?>"><button class="dugme tehlike" data-onay="Bu kayıt silinsin mi? (KVKK silme talebi)">Sil</button></form>
    </div>
  </section>
<?php endif; ?>

<?php if (!$kayitlar): ?>
  <div class="kart bos-durum"><p class="soluk">Henüz gönderim yok.</p></div>
<?php else: ?>
<form method="post" class="kart tablo-kart">
  <?= bz_csrf_field() ?>
  <div class="toplu">
    <select name="eylem"><option value="okundu">Okundu işaretle</option><option value="sil">Sil</option></select>
    <button class="dugme kucuk hayalet" data-onay="Seçili kayıtlara işlem uygulansın mı?">Uygula</button>
  </div>
  <table class="tablo">
    <thead><tr><th class="dar"><input type="checkbox" data-hepsini-sec></th><th>Gönderen</th><th>Özet</th><th>Form</th><th>Tarih</th></tr></thead>
    <tbody>
    <?php foreach ($kayitlar as $k): $d = json_decode((string) $k['veri'], true) ?: []; ?>
      <tr class="<?= $k['okundu'] ? '' : 'okunmamis' ?>">
        <td><input type="checkbox" name="secili[]" value="<?= $k['id'] ?>"></td>
        <td><a href="<?= e(App::adminUrl('formlar', array_filter(['form' => $form, 'id' => $k['id']]))) ?>"><?= e($d['ad_soyad'] ?? $d['ad'] ?? $d['eposta'] ?? '#' . $k['id']) ?></a></td>
        <td class="soluk"><?= e(Str::excerpt($d['mesaj'] ?? implode(' · ', $d), 70)) ?></td>
        <td><span class="etiket"><?= e($k['form']) ?></span></td>
        <td><?= Str::date($k['tarih'], 'd M Y H:i') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</form>
<?php endif; ?>
