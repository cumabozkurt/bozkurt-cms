<?php use Bozkurt\App; $asset = App::$basePath . 'yonetim/assets/'; ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>İki adımlı doğrulama</title>
<link rel="stylesheet" href="<?= $asset ?>yonetim.css?v=<?= BZ_VERSION ?>">
<script>document.documentElement.dataset.tema=localStorage.getItem('bz_tema')||'acik'</script>
</head>
<body class="giris-sayfa">
<form class="giris-kutu" method="post" action="<?= e(App::adminUrl('dogrulama')) ?>">
  <img src="<?= $asset ?>logo.svg" alt="" width="56" height="56">
  <h1>İki adımlı doğrulama</h1>
  <p class="soluk">Doğrulama uygulamanızdaki 6 haneli kodu girin.</p>
  <?php if ($hata): ?><div class="bildirim hata"><?= e($hata) ?></div><?php endif; ?>
  <?= bz_csrf_field() ?>
  <label>Kod<input name="kod" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus class="kod-girdi"></label>
  <button class="dugme tam">Doğrula</button>
</form>
</body>
</html>
