<?php use Bozkurt\App; $asset = App::$basePath . 'yonetim/assets/'; ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><meta name="referrer" content="no-referrer">
<title>Yeni şifre</title>
<link rel="stylesheet" href="<?= $asset ?>yonetim.css?v=<?= BZ_VERSION ?>">
</head>
<body class="giris-sayfa">
<form class="giris-kutu" method="post">
  <img src="<?= $asset ?>logo.svg" alt="" width="56" height="56">
  <h1>Yeni şifre belirleyin</h1>
  <?php if (!$gecerli): ?>
    <div class="bildirim hata">Bu bağlantı geçersiz veya süresi dolmuş.</div>
    <p class="soluk kucuk-metin"><a href="<?= e(App::adminUrl('sifremi_unuttum')) ?>">Yeni bağlantı isteyin</a></p>
  <?php else: ?>
    <?php if ($hata): ?><div class="bildirim hata"><?= e($hata) ?></div><?php endif; ?>
    <?= bz_csrf_field() ?>
    <label>Yeni şifre (en az 10 karakter)<input type="password" name="sifre" minlength="10" required autocomplete="new-password" autofocus></label>
    <label>Yeni şifre (tekrar)<input type="password" name="sifre2" minlength="10" required autocomplete="new-password"></label>
    <button class="dugme tam">Şifreyi kaydet</button>
  <?php endif; ?>
</form>
</body>
</html>
