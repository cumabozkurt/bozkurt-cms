<?php use Bozkurt\App; $asset = App::$basePath . 'yonetim/assets/'; ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Giriş · <?= e(App::setting('site_adi', 'BOZKURT CMS')) ?></title>
<link rel="icon" href="<?= $asset ?>logo.svg">
<link rel="stylesheet" href="<?= $asset ?>yonetim.css?v=<?= BZ_VERSION ?>">
<script>document.documentElement.dataset.tema=localStorage.getItem('bz_tema')||(matchMedia('(prefers-color-scheme: dark)').matches?'koyu':'acik')</script>
</head>
<body class="giris-sayfa">
<form class="giris-kutu" method="post" action="<?= e(App::adminUrl('giris')) ?>">
  <img src="<?= $asset ?>logo.svg" alt="" width="56" height="56">
  <h1><?= e(App::setting('site_adi', 'BOZKURT CMS')) ?></h1>
  <p class="soluk">Yönetim paneline giriş yapın</p>
  <?php if ($hata): ?><div class="bildirim hata"><?= e($hata) ?></div><?php endif; ?>
  <?= bz_csrf_field() ?>
  <label>E-posta<input type="email" name="eposta" value="<?= e($eposta) ?>" required autofocus autocomplete="username"></label>
  <label>Şifre<input type="password" name="sifre" required autocomplete="current-password"></label>
  <button class="dugme tam">Giriş yap</button>
  <?php foreach (bz_flash() ?? [] as $f): ?><div class="bildirim <?= e($f['tur']) ?>"><?= e($f['mesaj']) ?></div><?php endforeach; ?>
  <p class="soluk kucuk-metin"><a href="<?= e(App::adminUrl('sifremi_unuttum')) ?>">Şifremi unuttum</a> · <a href="<?= e(App::$basePath) ?>">← Siteye dön</a></p>
</form>
</body>
</html>
