<?php
use Bozkurt\App;
use Bozkurt\Str;

$baslik = 'Medya';
?>
<div class="sayfa-ust">
  <div><h1>Medya Kütüphanesi</h1><p class="soluk">Resimler otomatik küçültülür, konum bilgisi (EXIF) temizlenir.</p></div>
</div>
<form class="yukleme-alani" method="post" enctype="multipart/form-data" data-surukle-birak>
  <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="yukle">
  <?= bz_ikon('yedek', 28) ?>
  <p><strong>Dosyaları buraya sürükleyin</strong> ya da <label class="baglanti">bilgisayardan seçin<input type="file" name="dosya[]" multiple hidden onchange="this.form.submit()"></label></p>
  <small class="soluk">En fazla <?= e(App::setting('azami_yukleme_mb', '20')) ?> MB · JPG, PNG, WebP, GIF, PDF, Office, ZIP, MP4</small>
</form>
<form class="filtre-cubugu" method="get">
  <input type="hidden" name="s" value="medya">
  <div class="arama-kutu"><?= bz_ikon('arama', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Dosya adı veya alt metin…"></div>
  <select name="tur" onchange="this.form.submit()">
    <option value="">Tümü</option><option value="resim" <?= $tur === 'resim' ? 'selected' : '' ?>>Resimler</option><option value="belge" <?= $tur === 'belge' ? 'selected' : '' ?>>Belgeler</option><option value="altsiz" <?= $tur === 'altsiz' ? 'selected' : '' ?>>Alt metni eksik</option>
  </select>
</form>
<?php if (!$ogeler): ?><div class="kart bos-durum"><p class="soluk">Kütüphane boş.</p></div><?php endif; ?>
<div class="medya-galeri">
  <?php foreach ($ogeler as $m): $url = bz_upload_url($m['dosya']); $img = str_starts_with((string) $m['mime'], 'image/'); ?>
    <figure class="medya-oge">
      <a href="<?= e($url) ?>" target="_blank" class="medya-kare"><?php if ($img): ?><img src="<?= e($url) ?>" alt="<?= e($m['alt']) ?>" loading="lazy"><?php else: ?><span class="dosya-ikon"><?= e(strtoupper(pathinfo($m['dosya'], PATHINFO_EXTENSION))) ?></span><?php endif; ?></a>
      <figcaption>
        <span class="kirp" title="<?= e($m['orijinal']) ?>"><?= e($m['orijinal']) ?></span>
        <small class="soluk"><?= Str::bytes((int) $m['boyut']) ?><?= $m['genislik'] ? ' · ' . $m['genislik'] . '×' . $m['yukseklik'] : '' ?></small>
        <?php if ($img): ?><div class="alt-satir"><input class="alt-girdi" value="<?= e($m['alt']) ?>" placeholder="Alt metin (erişilebilirlik)" data-alt-kaydet="<?= $m['id'] ?>"><button type="button" class="ikon-dugme yz-dugme" title="YZ ile alt metin üret" data-yz-alt="<?= e($m['dosya']) ?>">✨</button></div><?php endif; ?>
        <div class="medya-eylem">
          <button type="button" class="baglanti" data-kopyala="<?= e(App::siteUrl() . $url) ?>">Bağlantıyı kopyala</button>
          <form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="sil"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="baglanti tehlike-metin" data-onay="Dosya kalıcı olarak silinsin mi? Kullanıldığı sayfalarda görünmez olur.">Sil</button></form>
        </div>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
