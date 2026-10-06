<?php
use Bozkurt\App;
use Bozkurt\Auth;
use Bozkurt\Content;
use Bozkurt\Str;

$coklu = $sablon['meta']['coklu'];
$baslik = $coklu ? ($kayit ? 'Düzenle: ' . $meta['baslik'] : ($kaynak ? __('Çeviri:') . ' ' . $meta['baslik'] : __('Yeni') . ' ' . Str::lower($sablon['meta']['tekil_adi'] ?? $sablon['meta']['baslik']))) : $sablon['meta']['baslik'];
$gruplar = bz_alan_gruplari($sablon['alanlar']);
$onizleme = $kayit ? Content::url($kayit) . ($meta['durum'] !== 'yayinda' ? '?onizleme=1' : '') : null;
$cokDilli = App::isMultilingual();
?>
<form method="post" class="duzenleyici" data-kaydet-kisayol data-otomatik-kayit="<?= e($ad . ':' . ($id ?: 'yeni') . ':' . $dil) ?>" data-dil="<?= e($dil) ?>" novalidate>
  <?= bz_csrf_field() ?>
  <div class="sayfa-ust yapiskan">
    <div>
      <?php if ($coklu): ?><a class="geri" href="<?= e(App::adminUrl('icerik', ['sablon' => $ad, 'dil' => $dil])) ?>">← <?= e($sablon['meta']['baslik']) ?></a><?php endif; ?>
      <h1><?= e($baslik) ?><?php if ($cokDilli): ?> <span class="etiket"><?= e(strtoupper($dil)) ?></span><?php endif; ?></h1>
    </div>
    <div class="dugme-grubu">
      <?php if ($onizleme): ?><a class="dugme hayalet" href="<?= e($onizleme) ?>" target="_blank"><?= bz_ikon('goz', 16) ?> Önizle</a><?php endif; ?>
      <?php if ($coklu): ?><button class="dugme hayalet" name="eylem" value="taslak">Taslak kaydet</button><?php endif; ?>
      <button class="dugme" name="eylem" value="yayinla"><?= $coklu && Auth::can('yayinla') ? 'Yayınla' : 'Kaydet' ?> <kbd>Ctrl S</kbd></button>
    </div>
  </div>

  <?php if ($cokDilli): ?>
  <nav class="dil-sekmeleri" aria-label="Diller">
    <?php foreach (App::languages() as $l): $tr = $ceviriler[$l] ?? null; ?>
      <?php if ($l === $dil): ?>
        <span class="aktif"><?= e(App::langName($l)) ?></span>
      <?php elseif ($tr): ?>
        <a href="<?= e(App::adminUrl('duzenle', ['id' => $tr['id']])) ?>"><?= e(App::langName($l)) ?> ✓</a>
      <?php elseif (!$coklu): ?>
        <a href="<?= e(App::adminUrl('duzenle', ['sablon' => $ad, 'dil' => $l])) ?>"><?= e(App::langName($l)) ?></a>
      <?php elseif ($kayit): ?>
        <a class="eksik" href="<?= e(App::adminUrl('duzenle', ['sablon' => $ad, 'dil' => $l, 'kaynak' => $kayit['id']])) ?>">+ <?= e(App::langName($l)) ?></a>
      <?php else: ?>
        <span class="soluk"><?= e(App::langName($l)) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($kaynak || ($kayit && $dil !== App::defaultLang())): ?>
      <button type="button" class="dugme kucuk yz-dugme" data-yz-cevir="<?= e($dil) ?>">✨ Tüm alanları <?= e(App::langName($dil)) ?> diline çevir</button>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

  <div class="bildirim uyari" data-otomatik-kayit-uyari hidden>Bu sayfada kaydedilmemiş bir taslağınız var (<span></span>). <button type="button" class="baglanti" data-otomatik-geri>Geri yükle</button> · <button type="button" class="baglanti" data-otomatik-sil>Yok say</button></div>
  <?php if ($hatalar): ?><div class="bildirim hata">Lütfen işaretli alanları düzeltin.</div><?php endif; ?>

  <div class="duzen-izgara">
    <div class="duzen-ana">
      <?php if ($coklu): ?>
      <section class="kart">
        <div class="alan <?= isset($hatalar['baslik']) ? 'hatali' : '' ?>">
          <label for="baslik">Başlık <b class="zorunlu">*</b></label>
          <input id="baslik" class="buyuk-girdi" name="baslik" value="<?= e($meta['baslik']) ?>" required data-slug-kaynak data-cevrilebilir autofocus maxlength="255">
          <?php if (isset($hatalar['baslik'])): ?><small class="hata-metni"><?= e($hatalar['baslik']) ?></small><?php endif; ?>
        </div>
        <div class="alan slug-alan">
          <label for="slug">Adres</label>
          <div class="onek-kutu"><span><?= e(App::siteUrl() . App::url($ad, $dil)) ?>/</span><input id="slug" name="slug" value="<?= e($meta['slug']) ?>" data-slug-hedef placeholder="otomatik"></div>
          <?php if ($kayit && $kayit['durum'] === 'yayinda'): ?><small class="yardim">Adresi değiştirirseniz eski adresten otomatik 301 yönlendirme eklenir.</small><?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php foreach ($gruplar as $grup => $alanlar): ?>
        <section class="kart">
          <?php if (count($gruplar) > 1): ?><h2><?= e($grup) ?></h2><?php endif; ?>
          <div class="alanlar">
            <?php foreach ($alanlar as $alan => $def): ?>
              <?= bz_alan($def, $degerler[$alan] ?? '', 'alan[' . $alan . ']', $hatalar, $alan) ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
      <?php if (!$sablon['alanlar']): ?>
        <div class="kart bos-durum"><p>Bu şablonda düzenlenebilir alan yok. HTML dosyasına <code>&lt;bz:alan ad="..."&gt;</code> ekleyin.</p></div>
      <?php endif; ?>
    </div>

    <aside class="duzen-yan">
      <?php if ($coklu): ?>
      <section class="kart">
        <h3>Yayın</h3>
        <p><span class="etiket <?= e($meta['durum']) ?>"><?= $meta['durum'] === 'yayinda' ? (strtotime($meta['yayin_tarihi']) > time() ? 'Zamanlandı' : 'Yayında') : 'Taslak' ?></span></p>
        <div class="alan"><label for="yt">Yayın tarihi</label>
          <input type="datetime-local" id="yt" name="yayin_tarihi" value="<?= e(date('Y-m-d\TH:i', strtotime($meta['yayin_tarihi']))) ?>">
          <small class="yardim">İleri bir tarih seçerseniz içerik o anda yayınlanır.</small></div>
        <div class="alan"><label for="sira">Sıra</label><input type="number" id="sira" name="sira" value="<?= (int) $meta['sira'] ?>"></div>
      </section>
      <?php endif; ?>

      <section class="kart">
        <div class="kart-baslik"><h3>SEO ve paylaşım</h3><span class="seo-puan" data-seo-puan title="SEO puanı">–</span></div>
        <div class="google-onizleme" data-serp>
          <span class="serp-url"><?= e(parse_url(App::siteUrl(), PHP_URL_HOST)) ?> › <?= e($ad) ?></span>
          <span class="serp-baslik" data-serp-baslik><?= e(($seo['baslik'] ?? '') ?: ($meta['baslik'] ?: $sablon['meta']['baslik'])) ?></span>
          <span class="serp-aciklama" data-serp-aciklama><?= e(($seo['aciklama'] ?? '') ?: 'Açıklama otomatik olarak içerikten oluşturulur.') ?></span>
        </div>
        <div class="alan"><label>Odak anahtar kelime</label><input name="seo[anahtar]" value="<?= e($seo['anahtar'] ?? '') ?>" maxlength="80" data-seo-anahtar placeholder="Örn: ankara web tasarım"></div>
        <div class="alan"><label>SEO başlığı <small class="sayac" data-sayac="60"></small></label><input name="seo[baslik]" value="<?= e($seo['baslik'] ?? '') ?>" maxlength="120" data-serp-kaynak="baslik" data-cevrilebilir placeholder="Boşsa sayfa başlığı kullanılır"></div>
        <div class="alan"><label>Meta açıklama <small class="sayac" data-sayac="160"></small></label><textarea name="seo[aciklama]" rows="3" maxlength="300" data-serp-kaynak="aciklama" data-cevrilebilir placeholder="Boşsa içerikten özetlenir"><?= e($seo['aciklama'] ?? '') ?></textarea></div>
        <button type="button" class="dugme kucuk hayalet yz-dugme" data-yz-seo>✨ YZ ile başlık ve açıklama üret</button>
        <ul class="seo-kontrol" data-seo-liste></ul>
        <?= bz_alan(['tur' => 'resim', 'etiket' => 'Paylaşım görseli (1200×630)', 'zorunlu' => false, 'yer_tutucu' => '', 'yardim' => '', 'genislik' => 'tam', 'en_fazla' => 0, 'secenekler' => []], $seo['resim'] ?? '', 'seo[resim]') ?>
        <label class="anahtar"><input type="checkbox" name="seo[noindex]" value="1" <?= !empty($seo['noindex']) ? 'checked' : '' ?>><span></span> Arama motorlarında gösterme</label>
      </section>

      <?php if ($surumler): ?>
      <section class="kart">
        <h3>Sürüm geçmişi</h3>
        <ul class="liste kompakt">
          <?php foreach ($surumler as $s): ?>
            <li><span><?= Str::date($s['tarih'], 'd M H:i') ?> <small class="soluk"><?= e($s['ad'] ?? '') ?></small></span>
              <button class="baglanti" form="surum-<?= $s['id'] ?>" data-onay="Bu sürüm geri yüklensin mi? Mevcut hâl de sürüm olarak saklanır.">Geri yükle</button></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <?php if ($kayit && $coklu): ?>
      <section class="kart tehlike">
        <div class="dugme-grubu">
          <button class="dugme hayalet" form="kopya-formu"><?= bz_ikon('sayfa', 16) ?> Kopyala</button>
          <button class="dugme tehlike" form="sil-formu" data-onay="“<?= e($meta['baslik']) ?>” kalıcı olarak silinsin mi?"><?= bz_ikon('cop', 16) ?> Sil</button>
        </div>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</form>

<?php foreach ($surumler as $s): ?>
  <form id="surum-<?= $s['id'] ?>" method="post" action="<?= e(App::adminUrl('surum')) ?>" hidden><?= bz_csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"></form>
<?php endforeach; ?>
<?php if ($kayit && $coklu): ?>
  <form id="sil-formu" method="post" action="<?= e(App::adminUrl('sil')) ?>" hidden><?= bz_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"></form>
  <form id="kopya-formu" method="post" action="<?= e(App::adminUrl('kopyala')) ?>" hidden><?= bz_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"></form>
<?php endif; ?>
