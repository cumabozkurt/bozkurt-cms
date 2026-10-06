<?php
use Bozkurt\App;
use Bozkurt\Auth;
use Bozkurt\Str;

$baslik = 'Kullanıcılar';
$d = $duzenlenen ?? ['id' => 0, 'ad' => '', 'eposta' => '', 'rol' => 'editor'];
?>
<div class="sayfa-ust"><div><h1>Kullanıcılar</h1><p class="soluk">Yönetici her şeyi; editör içerik, medya ve formları; yazar yalnızca kendi içeriklerini yönetir.</p></div></div>
<div class="duzen-izgara">
  <section class="kart tablo-kart duzen-ana">
    <table class="tablo">
      <thead><tr><th>Ad</th><th>Rol</th><th>2FA</th><th>Son giriş</th><th class="dar"></th></tr></thead>
      <tbody>
      <?php foreach ($kullanicilar as $k): ?>
        <tr>
          <td><strong><?= e($k['ad']) ?></strong><small class="soluk"><?= e($k['eposta']) ?></small></td>
          <td><span class="etiket"><?= e(Auth::ROLES[$k['rol']] ?? $k['rol']) ?></span></td>
          <td><?= $k['totp_gizli'] ? '🔒' : '—' ?></td>
          <td><?= $k['son_giris'] ? Str::date($k['son_giris'], 'once') : 'Hiç' ?></td>
          <td class="satir-eylem">
            <a class="baglanti" href="<?= e(App::adminUrl('kullanicilar', ['id' => $k['id']])) ?>">Düzenle</a>
            <?php if ($k['totp_gizli'] && (int) $k['id'] !== (int) $user['id']): ?>
              <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="2fa_sifirla"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="baglanti" data-onay="Telefonunu kaybeden kullanıcının 2FA'sı sıfırlansın mı?">2FA sıfırla</button></form>
            <?php endif; ?>
            <?php if ((int) $k['id'] !== (int) $user['id']): ?>
              <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="sil"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="baglanti tehlike-metin" data-onay="<?= e($k['ad']) ?> silinsin mi?">Sil</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <aside class="duzen-yan">
    <form method="post" class="kart">
      <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="kaydet"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
      <h3><?= $d['id'] ? 'Kullanıcıyı düzenle' : 'Yeni kullanıcı' ?></h3>
      <div class="alan"><label>Ad Soyad</label><input name="ad" value="<?= e($d['ad']) ?>" required></div>
      <div class="alan"><label>E-posta</label><input type="email" name="eposta" value="<?= e($d['eposta']) ?>" required></div>
      <div class="alan"><label>Rol</label><select name="rol"><?php foreach (Auth::ROLES as $r => $l): ?><option value="<?= $r ?>" <?= $d['rol'] === $r ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="alan"><label>Şifre <?= $d['id'] ? '<small class="soluk">(değiştirmek için doldurun)</small>' : '' ?></label><input type="password" name="sifre" minlength="10" autocomplete="new-password" <?= $d['id'] ? '' : 'required' ?>></div>
      <button class="dugme tam">Kaydet</button>
      <?php if ($d['id']): ?><p><a class="baglanti" href="<?= e(App::adminUrl('kullanicilar')) ?>">+ Yeni kullanıcı</a></p><?php endif; ?>
    </form>
  </aside>
</div>
