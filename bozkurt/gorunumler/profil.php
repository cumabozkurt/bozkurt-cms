<?php
$baslik = 'Profilim';
?>
<div class="sayfa-ust"><div><h1>Profilim</h1><p class="soluk"><?= e($user['ad']) ?> · <?= e($user['eposta']) ?></p></div></div>
<form method="post" class="kart" style="max-width:520px">
  <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="dil">
  <h2>Panel dili / Panel language</h2>
  <div class="dugme-grubu"><select name="dil" style="width:auto"><option value="tr" <?= ($user['dil'] ?? 'tr') === 'tr' ? 'selected' : '' ?>>Türkçe</option><option value="en" <?= ($user['dil'] ?? 'tr') === 'en' ? 'selected' : '' ?>>English</option></select><button class="dugme hayalet">Kaydet</button></div>
</form>
<div class="izgara-2">
  <form method="post" class="kart">
    <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="sifre">
    <h2>Şifre değiştir</h2>
    <div class="alan"><label>Mevcut şifre</label><input type="password" name="mevcut" required autocomplete="current-password"></div>
    <div class="alan"><label>Yeni şifre (en az 10 karakter)</label><input type="password" name="yeni" minlength="10" required autocomplete="new-password"></div>
    <div class="alan"><label>Yeni şifre (tekrar)</label><input type="password" name="yeni2" minlength="10" required autocomplete="new-password"></div>
    <button class="dugme">Şifreyi güncelle</button>
  </form>
  <section class="kart">
    <h2>İki adımlı doğrulama (2FA)</h2>
    <?php if (!empty($user['totp_gizli'])): ?>
      <p>🔒 Hesabınız iki adımlı doğrulama ile korunuyor.</p>
      <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="2fa_kapat">
        <div class="alan"><label>Kapatmak için şifreniz</label><input type="password" name="mevcut" required></div>
        <button class="dugme tehlike" data-onay="İki adımlı doğrulama kapatılsın mı?">Kapat</button></form>
    <?php elseif ($bekleyen): ?>
      <ol class="adimlar">
        <li>Google Authenticator, Microsoft Authenticator veya Authy uygulamasını açın.</li>
        <li>“Kurulum anahtarı gir” seçeneğiyle aşağıdaki anahtarı ekleyin:</li>
      </ol>
      <p class="gizli-anahtar"><code><?= e(trim(chunk_split($bekleyen, 4, ' '))) ?></code></p>
      <p class="soluk kucuk-metin">Mobilde: <a href="<?= e($uri) ?>">doğrulama uygulamasında aç</a></p>
      <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="2fa_onayla">
        <div class="alan"><label>Uygulamadaki 6 haneli kod</label><input name="kod" inputmode="numeric" maxlength="7" required class="kod-girdi"></div>
        <button class="dugme">Doğrula ve etkinleştir</button></form>
    <?php else: ?>
      <p class="soluk">Şifrenize ek olarak telefonunuzdaki kodla giriş yapın. Yönetici hesapları için şiddetle önerilir.</p>
      <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="2fa_baslat"><button class="dugme">Etkinleştir</button></form>
    <?php endif; ?>
  </section>
</div>
