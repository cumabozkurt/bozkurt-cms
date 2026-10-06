<?php
$baslik = 'Genel Alanlar';
$gruplar = bz_alan_gruplari($tanimlar);
?>
<form method="post" data-kaydet-kisayol>
  <?= bz_csrf_field() ?>
  <div class="sayfa-ust yapiskan">
    <div><h1>Genel Alanlar</h1><p class="soluk">Sitenin her yerinde kullanılan bilgiler: telefon, adres, sosyal medya… Şablonlarda <code>&lt;bz:genel&gt;</code> ile tanımlanır.</p></div>
    <button class="dugme">Kaydet <kbd>Ctrl S</kbd></button>
  </div>
  <?php if (\Bozkurt\App::isMultilingual()): ?>
  <nav class="sekmeler"><?php foreach (\Bozkurt\App::languages() as $l): ?><a class="<?= $l === $dil ? 'aktif' : '' ?>" href="<?= e(\Bozkurt\App::adminUrl('genel', ['dil' => $l])) ?>"><?= e(\Bozkurt\App::langName($l)) ?></a><?php endforeach; ?></nav>
  <?php if ($dil !== \Bozkurt\App::defaultLang()): ?><p class="soluk">Boş bıraktığınız alanlarda varsayılan dildeki değer kullanılır.</p><?php endif; ?>
  <?php endif; ?>
  <?php if (!$tanimlar): ?><div class="kart bos-durum"><p>Henüz genel alan yok. Örn: <code>&lt;bz:genel ad="telefon" tur="telefon" etiket="Telefon" /&gt;</code></p></div><?php endif; ?>
  <?php foreach ($gruplar as $g => $alanlar): ?>
    <section class="kart">
      <h2><?= e($g) ?></h2>
      <div class="alanlar">
        <?php foreach ($alanlar as $ad => $def): ?><?= bz_alan($def, $degerler[$ad] ?? '', 'alan[' . $ad . ']', $hatalar, $ad) ?><?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</form>
