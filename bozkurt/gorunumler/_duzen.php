<?php
use Bozkurt\App;
use Bozkurt\Auth;

require_once __DIR__ . '/_yardim.php';
$asset = App::$basePath . 'yonetim/assets/';
$v = BZ_VERSION;
$unread = Auth::can('formlar') ? (int) App::db()->val('SELECT COUNT(*) FROM bz_formlar WHERE okundu = 0') : 0;
$cur = $_GET['sablon'] ?? null;
if (!$cur && ($section === 'duzenle') && isset($kayit['sablon'])) {
    $cur = $kayit['sablon'];
}
$nav = function (string $s, string $label, string $icon, array $params = [], ?int $badge = null) use ($section, $cur) {
    $active = $section === $s && (($params['sablon'] ?? null) === null || ($params['sablon'] ?? null) === $cur);
    return '<a href="' . e(App::adminUrl($s, $params)) . '" class="' . ($active ? 'aktif' : '') . '">' . bz_ikon($icon) .
        '<span>' . e($label) . '</span>' . ($badge ? '<em class="rozet">' . $badge . '</em>' : '') . '</a>';
};
?><!doctype html>
<html lang="tr" data-tema="acik">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= bz_csrf_token() ?>">
<title><?= e(($baslik ?? 'Yönetim') . ' · ' . App::setting('site_adi', 'BOZKURT CMS')) ?></title>
<link rel="icon" href="<?= $asset ?>logo.svg">
<link rel="stylesheet" href="<?= $asset ?>yonetim.css?v=<?= $v ?>">
<script>document.documentElement.dataset.tema=localStorage.getItem('bz_tema')||(matchMedia('(prefers-color-scheme: dark)').matches?'koyu':'acik')</script>
</head>
<body data-taban="<?= e(App::$basePath) ?>" data-medya-url="<?= e(App::adminUrl('medya', ['json' => 1])) ?>" data-yz="<?= \Bozkurt\Ai::enabled() ? '1' : '0' ?>" data-yz-url="<?= e(App::adminUrl('yz')) ?>" data-panel-dil="<?= e(\Bozkurt\Lang::adminLang()) ?>">
<div class="kabuk">
  <aside class="yan">
    <?php $panelAdi = (string) App::setting('panel_adi', ''); $panelLogo = (string) App::setting('panel_logo', ''); ?>
    <a class="marka" href="<?= e(App::adminUrl()) ?>"><img src="<?= $panelLogo ? e(bz_upload_url($panelLogo)) : $asset . 'logo.svg' ?>" alt="" width="30" height="30"><span><?= $panelAdi !== '' ? e($panelAdi) : 'BOZKURT<small>CMS</small>' ?></span></a>
    <nav>
      <?= $nav('panel', 'Panel', 'panel') ?>
      <div class="yan-baslik">İçerik</div>
      <?php foreach ($templates as $tn => $t): if (!$t['alanlar'] && !$t['meta']['coklu']) continue; ?>
        <?= $nav($t['meta']['coklu'] ? 'icerik' : 'duzenle', $t['meta']['baslik'], $t['meta']['ikon'] ?? 'sayfa', ['sablon' => $tn]) ?>
      <?php endforeach; ?>
      <?php if (Auth::can('genel')): ?><?= $nav('genel', 'Genel Alanlar', 'dunya') ?><?php endif; ?>
      <?= $nav('takvim', 'İçerik Takvimi', 'takvim') ?>
      <div class="yan-baslik">Araçlar</div>
      <?= $nav('medya', 'Medya', 'medya') ?>
      <?php if (Auth::can('formlar')): ?><?= $nav('formlar', 'Form Mesajları', 'form', [], $unread) ?><?php endif; ?>
      <?php if (Auth::can('formlar') && \Bozkurt\Payment::provider() !== ''): ?><?= $nav('siparisler', 'Siparişler', 'sepet') ?><?php endif; ?>
      <?php if (Auth::can('genel')): ?><?= $nav('seo', 'SEO Denetimi', 'grafik') ?><?= $nav('yonlendirmeler', 'Yönlendirmeler', 'yon') ?><?php endif; ?>
      <?php if (Auth::can('*')): ?>
        <div class="yan-baslik">Sistem</div>
        <?= $nav('kullanicilar', 'Kullanıcılar', 'kullanici') ?>
        <?= $nav('ayarlar', 'Ayarlar', 'ayar') ?>
        <?= $nav('api', 'API ve MCP', 'anahtar') ?>
        <?= $nav('araclar', 'Araçlar', 'arac') ?>
        <?= $nav('yedek', 'Yedekleme', 'yedek') ?>
        <?= $nav('gunluk', 'Etkinlik Günlüğü', 'gunluk') ?>
        <?= $nav('sistem', 'Sistem Durumu', 'sistem') ?>
      <?php endif; ?>
    </nav>
    <div class="yan-alt">
      <a href="<?= e(App::$basePath) ?>" target="_blank"><?= bz_ikon('goz') ?><span>Siteyi görüntüle</span></a>
    </div>
  </aside>
  <div class="ana">
    <header class="ust-cubuk">
      <button class="menu-dugme" type="button" data-menu aria-label="Menü">☰</button>
      <div class="ust-baslik"><?= e($baslik ?? '') ?></div>
      <div class="ust-sag">
        <button type="button" class="ikon-dugme" data-tema-degistir title="Koyu / açık tema"><?= bz_ikon('ay') ?></button>
        <a class="kullanici-cip" href="<?= e(App::adminUrl('profil')) ?>"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['ad'], 0, 1))) ?></span><span><?= e($user['ad']) ?></span></a>
        <form method="post" action="<?= e(App::adminUrl('cikis')) ?>"><?= bz_csrf_field() ?><button class="ikon-dugme" title="Çıkış yap"><?= bz_ikon('cikis') ?></button></form>
      </div>
    </header>
    <main class="icerik-alan">
      <?php foreach ($flash ?? [] as $f): ?>
        <div class="bildirim <?= e($f['tur']) ?>" role="status"><?= e($f['mesaj']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
    <footer class="alt-bilgi"><?= App::setting('panel_adi') ? e(App::setting('panel_adi')) : 'BOZKURT CMS ' . $v . ' · Açık kaynak (MIT) · Türkiye\'de üretildi 🇹🇷' ?></footer>
  </div>
</div>
<div class="modal" id="yz-modal" hidden>
  <div class="modal-kutu dar">
    <div class="modal-ust"><strong>✨ Yapay zekâ yardımcısı</strong><button type="button" class="ikon-dugme" data-modal-kapat>✕</button></div>
    <div class="yz-govde">
      <div class="yz-gorevler">
        <button type="button" class="dugme kucuk hayalet" data-yz-gorev="duzelt">Yazımı düzelt</button>
        <button type="button" class="dugme kucuk hayalet" data-yz-gorev="ozet">Özet çıkar</button>
        <button type="button" class="dugme kucuk hayalet" data-yz-gorev="baslik">Başlık öner</button>
      </div>
      <label>Serbest komut<textarea rows="2" data-yz-komut placeholder="Örn: Bu metni daha samimi yap, sonuna eyleme çağrı ekle"></textarea></label>
      <button type="button" class="dugme kucuk" data-yz-gorev="serbest">Uygula</button>
      <div class="yz-sonuc" data-yz-sonuc hidden></div>
      <div class="dugme-grubu" data-yz-eylemler hidden><button type="button" class="dugme kucuk" data-yz-uygula>Metnin yerine koy</button><button type="button" class="dugme kucuk hayalet" data-yz-ekle>Sonuna ekle</button></div>
    </div>
  </div>
</div>
<div class="modal" id="medya-modal" hidden>
  <div class="modal-kutu">
    <div class="modal-ust"><strong>Medya Kütüphanesi</strong><button type="button" class="ikon-dugme" data-modal-kapat>✕</button></div>
    <div class="modal-arac">
      <input type="search" placeholder="Ara…" data-medya-ara>
      <label class="dugme kucuk">Yükle<input type="file" multiple hidden data-medya-yukle></label>
    </div>
    <div class="medya-izgara" data-medya-izgara><p class="soluk">Yükleniyor…</p></div>
  </div>
</div>
<?php if (\Bozkurt\Lang::adminLang() === 'en'): ?><script>window.BZ_CEVIRI=<?= json_encode(require BZ_CORE . '/lang/panel-en-js.php', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script><?php endif; ?>
<script src="<?= $asset ?>yonetim.js?v=<?= $v ?>"></script>
</body>
</html>
