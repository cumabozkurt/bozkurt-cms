<?php use Bozkurt\App; $asset = App::$basePath . 'yonetim/assets/'; ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Şifremi unuttum</title>
<link rel="stylesheet" href="<?= $asset ?>yonetim.css?v=<?= BZ_VERSION ?>">
<script>document.documentElement.dataset.tema=localStorage.getItem('bz_tema')||'acik'</script>
</head>
<body class="giris-sayfa">
<form class="giris-kutu" method="post" action="<?= e(App::adminUrl('sifremi_unuttum')) ?>">
  <img src="<?= $asset ?>logo.svg" alt="" width="56" height="56">
  <h1>Şifremi unuttum</h1>
  <?php if ($gonderildi): ?>
    <div class="bildirim basari">Bu adres kayıtlıysa birkaç dakika içinde bir sıfırlama bağlantısı gönderdik. Gereksiz klasörünü de kontrol edin.</div>
  <?php else: ?>
    <p class="soluk">Hesabınızın e-posta adresini girin, size bir sıfırlama bağlantısı gönderelim.</p>
    <?= bz_csrf_field() ?>
    <label>E-posta<input type="email" name="eposta" required autofocus autocomplete="username"></label>
    <button class="dugme tam">Bağlantı gönder</button>
  <?php endif; ?>
  <p class="soluk kucuk-metin"><a href="<?= e(App::adminUrl('giris')) ?>">← Girişe dön</a></p>
</form>
</body>
</html>
