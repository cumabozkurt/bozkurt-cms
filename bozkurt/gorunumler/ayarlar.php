<?php
use Bozkurt\App;

$baslik = 'Ayarlar';
$cb = fn(string $k, string $label, string $help = '') => '<label class="anahtar"><input type="checkbox" name="' . $k . '" value="1"' . ($a[$k] === '1' ? ' checked' : '') . '><span></span> ' . e($label) . '</label>' . ($help ? '<small class="yardim">' . e($help) . '</small>' : '');
$tx = fn(string $k, string $label, string $help = '', string $type = 'text', string $ph = '') => '<div class="alan"><label for="ay_' . $k . '">' . e($label) . '</label><input id="ay_' . $k . '" type="' . $type . '" name="' . $k . '" value="' . ($type === 'password' ? '' : e($a[$k])) . '" placeholder="' . e($type === 'password' && $a[$k] !== '' ? '•••••••• (kayıtlı)' : $ph) . '" autocomplete="off">' . ($help ? '<small class="yardim">' . e($help) . '</small>' : '') . '</div>';
$ta = fn(string $k, string $label, string $help = '', int $rows = 3, string $ph = '') => '<div class="alan"><label for="ay_' . $k . '">' . e($label) . '</label><textarea id="ay_' . $k . '" name="' . $k . '" rows="' . $rows . '" placeholder="' . e($ph) . '">' . e($a[$k]) . '</textarea>' . ($help ? '<small class="yardim">' . e($help) . '</small>' : '') . '</div>';
$sel = fn(string $k, string $label, array $opts, string $help = '') => '<div class="alan"><label for="ay_' . $k . '">' . e($label) . '</label><select id="ay_' . $k . '" name="' . $k . '">' . implode('', array_map(fn($v, $l) => '<option value="' . e($v) . '"' . ($a[$k] === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>', array_keys($opts), $opts)) . '</select>' . ($help ? '<small class="yardim">' . e($help) . '</small>' : '') . '</div>';
$img = fn(string $k, string $label) => bz_alan(['tur' => 'resim', 'etiket' => $label, 'zorunlu' => false, 'yer_tutucu' => '', 'yardim' => '', 'genislik' => 'tam', 'en_fazla' => 0, 'secenekler' => []], $a[$k], $k);
?>
<form method="post" data-kaydet-kisayol>
  <?= bz_csrf_field() ?><input type="hidden" name="_sekme" value="genel" data-sekme-alani>
  <div class="sayfa-ust yapiskan">
    <div><h1>Ayarlar</h1></div>
    <div class="dugme-grubu">
      <button class="dugme hayalet" name="eylem" value="onbellek">Önbelleği temizle</button>
      <button class="dugme" name="eylem" value="kaydet">Kaydet <kbd>Ctrl S</kbd></button>
    </div>
  </div>

  <div class="sekme-kutu" data-sekmeler>
    <nav class="sekmeler">
      <a href="#genel" class="aktif">Genel</a><a href="#diller">Diller</a><a href="#seo">SEO ve Pazarlama</a><a href="#yz">Yapay Zekâ</a><a href="#kvkk">KVKK</a>
      <a href="#eposta">E-posta</a><a href="#turkiye">Türkiye</a><a href="#odeme">Ödeme</a><a href="#performans">Performans</a><a href="#gelismis">Gelişmiş</a>
    </nav>

    <section class="kart" id="genel">
      <div class="alanlar">
        <?= $tx('site_adi', 'Site adı') ?>
        <?= $tx('site_slogan', 'Slogan', 'Ana sayfa başlığında kullanılır.') ?>
        <?= $ta('site_aciklama', 'Site açıklaması', 'Arama sonuçlarında ve paylaşımlarda varsayılan açıklama.', 2) ?>
        <?= $tx('site_url', 'Sitenin kanonik adresi', 'Canonical, sitemap ve e-postalarda kullanılır. Örn: https://www.firmaniz.com.tr', 'url') ?>
        <?= $img('logo', 'Logo') ?>
        <?= $img('favicon', 'Favicon') ?>
        <?= $cb('bakim_modu', 'Bakım modu', 'Açıkken siteyi yalnızca giriş yapmış kullanıcılar görür (HTTP 503).') ?>
      </div>
    </section>

    <section class="kart" id="diller" hidden>
      <div class="alanlar">
        <?= $tx('diller', 'Etkin diller', 'Virgülle ayırın; ilki varsayılan dildir. Örn: tr,en,de,ar — diğer diller /en/, /de/ önekiyle yayınlanır.', 'text', 'tr,en') ?>
        <p class="soluk kucuk-metin"><span>Desteklenen kodlar:</span> <?= e(implode(', ', array_map(fn($k, $v) => "$k ($v)", array_keys(App::LANG_NAMES), App::LANG_NAMES))) ?></p>
        <p class="soluk kucuk-metin">Şablon metinlerini çevirmek için <code>sablonlar/diller/en.json</code> dosyasına <code>{"Devamını oku": "Read more"}</code> biçiminde ekleyip şablonda <code>{{ "Devamını oku" | cevir }}</code> kullanın. Dil seçici: <code>&lt;bz:dil-secici /&gt;</code>.</p>
      </div>
    </section>

    <section class="kart" id="seo" hidden>
      <div class="alanlar">
        <?= $img('varsayilan_resim', 'Varsayılan paylaşım görseli (1200×630)') ?>
        <?= $tx('google_dogrulama', 'Google Search Console doğrulama kodu', 'Yalnızca content="…" içindeki değer.') ?>
        <?= $tx('yandex_dogrulama', 'Yandex Webmaster doğrulama kodu') ?>
        <?= $tx('google_analitik', 'Google Analytics 4 ölçüm kimliği', 'Çerez onayı verilene kadar yüklenmez. Form gönderimleri generate_lead olayı olarak ölçülür.', 'text', 'G-XXXXXXXXXX') ?>
        <?= $tx('yandex_metrika', 'Yandex Metrica sayaç numarası', '', 'text', '12345678') ?>
        <?= $tx('meta_pixel', 'Meta (Facebook/Instagram) Pixel kimliği', 'Onaydan sonra yüklenir; form gönderimleri Lead olayı olarak ölçülür.', 'text', '1234567890') ?>
        <?= $ta('robots_ek', 'robots.txt ek kuralları', '', 3, "User-agent: AhrefsBot\nCrawl-delay: 10") ?>
        <?= $cb('arama_motoru_engelle', 'Arama motorlarını engelle', 'Geliştirme aşamasında açın; yayında mutlaka kapatın.') ?>
      </div>
    </section>

    <section class="kart" id="yz" hidden>
      <div class="alanlar">
        <?= $tx('yz_url', 'API adresi (OpenAI uyumlu)', 'OpenAI: https://api.openai.com/v1 · OpenRouter: https://openrouter.ai/api/v1 · Groq: https://api.groq.com/openai/v1 · Gemini: https://generativelanguage.googleapis.com/v1beta/openai · Ollama: http://localhost:11434/v1', 'url', 'https://api.openai.com/v1') ?>
        <?= $tx('yz_anahtar', 'API anahtarı', 'Yalnızca sunucuda saklanır, tarayıcıya gönderilmez.', 'password') ?>
        <?= $tx('yz_model', 'Model', 'Görselden alt metin için görüntü destekli bir model seçin (ör. gpt-4o-mini).', 'text', 'gpt-4o-mini') ?>
        <?= $tx('yz_ton', 'Marka üslubu', '', 'text', 'samimi ama profesyonel') ?>
        <?= $sel('yz_botlari', 'Yapay zekâ botları (robots.txt)', ['sadece_arama' => 'YZ arama botlarına izin ver, eğitim botlarını engelle (önerilen)', 'izin' => 'Tüm YZ botlarına izin ver', 'engelle' => 'Tüm YZ botlarını engelle'], 'ChatGPT Search, Perplexity gibi yanıt motorlarında görünmek için arama botlarına izin verin.') ?>
        <?= $cb('yz_llms', 'llms.txt ve llms-full.txt yayınla', 'Yapay zekâ asistanlarına sitenizin özetini ve içerik haritasını sunar.') ?>
        <?= $ta('llms_giris', 'llms.txt giriş metni', 'Şirketiniz, hizmetleriniz ve YZ\'nin bilmesini istediğiniz temel bilgiler.', 3) ?>
        <?= $cb('yz_markdown', 'Sayfaların Markdown sürümleri (.md)', 'Her sayfanın sonuna .md eklenerek temiz metin sürümü sunulur.') ?>
        <?= $cb('mcp_acik', 'MCP sunucusunu aç (/mcp)', 'Claude, ChatGPT, Cursor gibi istemciler API anahtarıyla içeriğinize erişir. Anahtarları API ve MCP bölümünden oluşturun.') ?>
      </div>
      <button class="dugme hayalet" name="eylem" value="yz_test">YZ bağlantısını test et</button>
    </section>

    <section class="kart" id="kvkk" hidden>
      <div class="alanlar">
        <?= $cb('cerez_bandi', 'Çerez onay bandını göster', 'Analitik ve pazarlama betikleri yalnızca “Tümünü kabul et” sonrası çalışır.') ?>
        <?= $ta('cerez_metni', 'Çerez bandı metni', '', 3, 'Varsayılan metin kullanılır') ?>
        <div class="alan"><label>Aydınlatma metni sayfası</label><select name="kvkk_sayfasi">
          <?php foreach ($templates as $tn => $t): if ($t['meta']['coklu']) continue; ?><option value="<?= e($tn) ?>" <?= ($a['kvkk_sayfasi'] ?: 'kvkk') === $tn ? 'selected' : '' ?>><?= e($t['meta']['baslik']) ?></option><?php endforeach; ?>
        </select></div>
        <?= $cb('ip_anonim', 'IP adreslerini anonimleştir', 'Form ve günlük kayıtlarında IP\'nin son bölümü sıfırlanır (veri minimizasyonu).') ?>
        <?= $tx('form_saklama_gun', 'Form verilerini saklama süresi (gün)', '0 = sınırsız. Süresi dolan gönderimler otomatik silinir.', 'number', '365') ?>
      </div>
    </section>

    <section class="kart" id="eposta" hidden>
      <div class="alanlar">
        <?= $tx('bildirim_eposta', 'Bildirimlerin gideceği adres', '', 'email') ?>
        <?= $tx('smtp_sunucu', 'SMTP sunucusu', 'Hostinger: smtp.hostinger.com · Yandex: smtp.yandex.com.tr · Gmail: smtp.gmail.com. Boşsa PHP mail() kullanılır.') ?>
        <div class="alan yarim"><label>Port</label><input name="smtp_port" value="<?= e($a['smtp_port'] ?: '465') ?>"></div>
        <div class="alan yarim"><label>Güvenlik</label><select name="smtp_guvenlik"><option value="ssl" <?= $a['smtp_guvenlik'] !== 'tls' ? 'selected' : '' ?>>SSL (465)</option><option value="tls" <?= $a['smtp_guvenlik'] === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option></select></div>
        <?= $tx('smtp_kullanici', 'Kullanıcı adı (e-posta)') ?>
        <?= $tx('smtp_sifre', 'Şifre', 'Boş bırakırsanız kayıtlı şifre korunur.', 'password') ?>
        <?= $tx('smtp_gonderen', 'Gönderen adresi', 'Genellikle kullanıcı adıyla aynı olmalı.', 'email') ?>
      </div>
      <button class="dugme hayalet" name="eylem" value="smtp_test">Test e-postası gönder</button>
    </section>

    <section class="kart" id="turkiye" hidden>
      <h3>İşletme bilgileri (Google/Yandex yerel arama şeması)</h3>
      <div class="alanlar">
        <?= $tx('isletme_adi', 'İşletme adı', 'Doluysa ana sayfaya LocalBusiness şeması eklenir.') ?>
        <?= $sel('isletme_turu', 'İşletme türü', ['LocalBusiness' => 'Genel işletme', 'Restaurant' => 'Restoran', 'Store' => 'Mağaza', 'MedicalBusiness' => 'Sağlık', 'Dentist' => 'Diş hekimi', 'LegalService' => 'Hukuk bürosu', 'RealEstateAgent' => 'Emlak', 'AutoRepair' => 'Oto servis', 'BeautySalon' => 'Güzellik salonu', 'Hotel' => 'Otel', 'EducationalOrganization' => 'Eğitim']) ?>
        <?= $tx('isletme_telefon', 'Telefon', '', 'tel') ?>
        <?= $tx('isletme_adres', 'Açık adres') ?>
        <div class="alan yarim"><label>İlçe</label><input name="isletme_ilce" value="<?= e($a['isletme_ilce']) ?>"></div>
        <div class="alan yarim"><label>İl</label><select name="isletme_il"><option value="">—</option><?php foreach (\Bozkurt\Validate::ILLER as $il): ?><option <?= $a['isletme_il'] === $il ? 'selected' : '' ?>><?= e($il) ?></option><?php endforeach; ?></select></div>
        <div class="alan yarim"><label>Enlem</label><input name="isletme_enlem" value="<?= e($a['isletme_enlem']) ?>" placeholder="39.9208"></div>
        <div class="alan yarim"><label>Boylam</label><input name="isletme_boylam" value="<?= e($a['isletme_boylam']) ?>" placeholder="32.8541"></div>
        <?= $ta('isletme_saatler', 'Çalışma saatleri', 'Her satıra bir kural (schema.org biçimi).', 3, "Mo-Fr 09:00-18:00\nSa 10:00-14:00") ?>
      </div>
      <h3>SMS bildirimi (Netgsm)</h3>
      <div class="alanlar">
        <div class="alan yarim"><label>Netgsm kullanıcı kodu</label><input name="netgsm_kullanici" value="<?= e($a['netgsm_kullanici']) ?>" autocomplete="off"></div>
        <div class="alan yarim"><label>Netgsm şifre</label><input type="password" name="netgsm_sifre" placeholder="<?= $a['netgsm_sifre'] ? '•••••••• (kayıtlı)' : '' ?>" autocomplete="off"></div>
        <div class="alan yarim"><label>Mesaj başlığı</label><input name="netgsm_baslik" value="<?= e($a['netgsm_baslik']) ?>"></div>
        <div class="alan yarim"><label>Bildirim gidecek numara</label><input name="sms_bildirim_no" value="<?= e($a['sms_bildirim_no']) ?>" placeholder="05xx xxx xx xx"></div>
      </div>
      <button class="dugme hayalet" name="eylem" value="sms_test">Test SMS'i gönder</button>
      <h3>Resmî tatiller</h3>
      <div class="alanlar"><?= $ta('ek_tatiller', 'Dinî bayramlar ve ek tatiller', 'Sabit tatiller (23 Nisan, 29 Ekim…) yerleşiktir. Her satıra: YYYY-AA-GG=Ad. Diyanet takviminden ekleyin.', 4, "2027-03-09=Ramazan Bayramı 1. gün") ?></div>
    </section>

    <section class="kart" id="odeme" hidden>
      <div class="alanlar">
        <?= $sel('odeme_saglayici', 'Ödeme sağlayıcısı', ['' => 'Kapalı', 'paytr' => 'PayTR', 'iyzico' => 'iyzico']) ?>
        <?= $cb('odeme_test', 'Test modu', 'Canlıya geçmeden önce sağlayıcının test kartlarıyla deneyin.') ?>
        <div class="alan yarim"><label>PayTR mağaza no</label><input name="paytr_magaza_no" value="<?= e($a['paytr_magaza_no']) ?>" autocomplete="off"></div>
        <div class="alan yarim"><label>PayTR mağaza anahtarı</label><input type="password" name="paytr_anahtar" placeholder="<?= $a['paytr_anahtar'] ? '•••••••• (kayıtlı)' : '' ?>" autocomplete="off"></div>
        <div class="alan yarim"><label>PayTR mağaza tuzu</label><input type="password" name="paytr_tuz" placeholder="<?= $a['paytr_tuz'] ? '•••••••• (kayıtlı)' : '' ?>" autocomplete="off"></div>
        <div class="alan yarim"><label>iyzico API anahtarı</label><input name="iyzico_api" value="<?= e($a['iyzico_api']) ?>" autocomplete="off"></div>
        <div class="alan yarim"><label>iyzico gizli anahtar</label><input type="password" name="iyzico_gizli" placeholder="<?= $a['iyzico_gizli'] ? '•••••••• (kayıtlı)' : '' ?>" autocomplete="off"></div>
      </div>
      <p class="soluk kucuk-metin">PayTR bildirim URL'si: <code><?= e(App::siteUrl()) ?>/odeme/paytr-bildirim</code> — PayTR mağaza panelinde tanımlayın. Ürün sayfasına <code>&lt;bz:odeme alan="fiyat" /&gt;</code> ekleyin; tutar her zaman sunucuda içerikten okunur.</p>
    </section>

    <section class="kart" id="performans" hidden>
      <div class="alanlar">
        <?= $cb('onbellek', 'Tam sayfa önbellek', 'Ziyaretçilere hazır HTML sunar. İçerik kaydedildiğinde otomatik temizlenir.') ?>
        <?= $tx('onbellek_sure', 'Önbellek süresi (saniye)', '', 'number', '3600') ?>
        <?= $tx('azami_yukleme_mb', 'En büyük yükleme (MB)', 'Sunucu sınırı: ' . ini_get('upload_max_filesize'), 'number', '20') ?>
        <?= $tx('azami_resim_genislik', 'Yüklenen resimlerin en büyük genişliği (px)', 'Daha büyükleri otomatik küçültülür.', 'number', '2400') ?>
      </div>
    </section>

    <section class="kart" id="gelismis" hidden>
      <div class="alanlar">
        <?= $cb('api_acik', 'JSON API okumayı herkese aç', 'Kapalıyken okuma için de API anahtarı gerekir. Yazma her zaman anahtar ister.') ?>
        <?= $tx('api_cors', 'CORS izinli köken', 'Boş = tarayıcıdan çapraz erişim kapalı. * veya https://uygulamam.com', 'text', '') ?>
        <?= $ta('webhook_adresleri', 'Web kancası adresleri', 'İçerik, form ve ödeme olaylarında JSON gönderilir (en fazla 5 adres, her satıra bir). Zapier, Make, n8n, Slack.', 3, 'https://hooks.zapier.com/...') ?>
        <?= $tx('webhook_gizli', 'Web kancası imza anahtarı', 'X-Bozkurt-Imza başlığı HMAC-SHA256 ile imzalanır.', 'password') ?>
        <?= $cb('guvenilir_vekil', 'Ters vekil / Cloudflare başlıklarına güven', 'Yalnızca site gerçekten Cloudflare veya bir vekil arkasındaysa açın; aksi hâlde saldırgan sahte IP ile hız sınırını atlatabilir.') ?>
        <?= $tx('panel_adi', 'Beyaz etiket: panel adı', 'Müşterilerinize kendi markanızla teslim edin.') ?>
        <?= $img('panel_logo', 'Beyaz etiket: panel logosu') ?>
      </div>
    </section>
  </div>
</form>
