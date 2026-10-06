<?php use Bozkurt\App; $asset = App::$basePath . 'yonetim/assets/'; ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>BOZKURT CMS Kurulumu</title>
<link rel="icon" href="<?= $asset ?>logo.svg">
<link rel="stylesheet" href="<?= $asset ?>yonetim.css?v=<?= BZ_VERSION ?>">
</head>
<body class="giris-sayfa">
<form class="giris-kutu genis" method="post">
  <img src="<?= $asset ?>logo.svg" alt="" width="56" height="56">
  <h1>BOZKURT CMS'e hoş geldiniz</h1>
  <p class="soluk">Kurulum tek ekran sürer. Bilgileri doldurun, gerisini biz halledelim.</p>

  <details class="gereksinimler" <?= $engeller ? 'open' : '' ?>>
    <summary><?= $engeller ? '⚠️ Bazı gereksinimler karşılanmıyor' : '✅ Sunucunuz hazır' ?></summary>
    <table class="tablo kompakt">
      <?php foreach ($kontroller as [$ad, $ok, $deger, $zorunlu]): ?>
        <tr><td><?= e($ad) ?></td><td><?= e($deger) ?></td><td><?= $ok ? '✅' : ($zorunlu ? '❌' : '➖') ?></td></tr>
      <?php endforeach; ?>
    </table>
  </details>

  <?php foreach ($engeller as $en): ?><div class="bildirim hata"><?= e($en[0]) ?></div><?php endforeach; ?>
  <?php foreach ($hatalar as $h): ?><div class="bildirim hata"><?= e($h) ?></div><?php endforeach; ?>
  <?php if (!$engeller): ?>
  <?= bz_csrf_field() ?>
  <fieldset>
    <legend>Site</legend>
    <label>Site adı<input name="site_adi" value="<?= e($v['site_adi']) ?>" required></label>
  </fieldset>
  <fieldset>
    <legend>Yönetici hesabı</legend>
    <div class="ikili">
      <label>Adınız<input name="ad" value="<?= e($v['ad']) ?>" required autocomplete="name"></label>
      <label>E-posta<input type="email" name="eposta" value="<?= e($v['eposta']) ?>" required autocomplete="email"></label>
    </div>
    <label>Şifre <small class="soluk">(en az 10 karakter)</small><input type="password" name="sifre" minlength="10" required autocomplete="new-password"></label>
  </fieldset>
  <fieldset>
    <legend>Veritabanı</legend>
    <div class="secim-kartlari">
      <label class="secim-kart"><input type="radio" name="surucu" value="sqlite" <?= $v['surucu'] === 'sqlite' ? 'checked' : '' ?> <?= extension_loaded('pdo_sqlite') ? '' : 'disabled' ?>>
        <span><strong>SQLite</strong><small>Önerilen · Ayar gerekmez</small></span></label>
      <label class="secim-kart"><input type="radio" name="surucu" value="mysql" <?= $v['surucu'] === 'mysql' ? 'checked' : '' ?> <?= extension_loaded('pdo_mysql') ? '' : 'disabled' ?>>
        <span><strong>MySQL / MariaDB</strong><small>hPanel › Veritabanları</small></span></label>
    </div>
    <div class="mysql-alanlari" data-mysql>
      <div class="ikili">
        <label>Sunucu<input name="db_sunucu" value="<?= e($v['db_sunucu']) ?>"></label>
        <label>Port<input name="db_port" value="<?= e($v['db_port']) ?>"></label>
      </div>
      <label>Veritabanı adı<input name="db_ad" value="<?= e($v['db_ad']) ?>" placeholder="u123456789_bozkurt"></label>
      <div class="ikili">
        <label>Kullanıcı<input name="db_kullanici" value="<?= e($v['db_kullanici']) ?>"></label>
        <label>Şifre<input type="password" name="db_sifre"></label>
      </div>
    </div>
  </fieldset>
  <label class="anahtar"><input type="checkbox" name="ornek" value="1" <?= $v['ornek'] === '1' ? 'checked' : '' ?>><span></span> Örnek içerikleri yükle (blog yazıları, ana sayfa kartları)</label>
  <button class="dugme tam">Kurulumu tamamla →</button>
  <?php endif; ?>
</form>
<script>
(function(){var m=document.querySelector('[data-mysql]');function g(){var r=document.querySelector('input[name=surucu]:checked');if(m)m.hidden=!r||r.value!=='mysql'}document.querySelectorAll('input[name=surucu]').forEach(function(i){i.addEventListener('change',g)});g()})();
</script>
</body>
</html>
