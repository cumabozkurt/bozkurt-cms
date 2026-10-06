<?php
use Bozkurt\App;
use Bozkurt\Str;

$baslik = 'API ve MCP';
$site = App::siteUrl();
?>
<div class="sayfa-ust"><div><h1>API ve MCP</h1><p class="soluk">İçeriğinizi mobil uygulamalara, Next.js/Nuxt ön yüzlerine, Zapier/n8n otomasyonlarına ve Claude/ChatGPT gibi yapay zekâ istemcilerine güvenle açın.</p></div></div>
<?php if ($yeni): ?>
  <div class="bildirim uyari"><strong>Yeni anahtarınız:</strong> <code class="secilebilir"><?= e($yeni) ?></code><br>Bu anahtar yalnızca şimdi gösterilir. Güvenli bir yere kaydedin.</div>
<?php endif; ?>
<div class="duzen-izgara">
  <div class="duzen-ana">
    <section class="kart tablo-kart">
      <table class="tablo">
        <thead><tr><th>Ad</th><th>Kapsam</th><th>Son kullanım</th><th>Oluşturma</th><th class="dar"></th></tr></thead>
        <tbody>
        <?php if (!$tokenlar): ?><tr><td colspan="5" class="soluk">Henüz anahtar yok.</td></tr><?php endif; ?>
        <?php foreach ($tokenlar as $t): ?>
          <tr><td><strong><?= e($t['ad']) ?></strong></td><td><span class="etiket <?= $t['kapsam'] === 'yaz' ? 'yeni' : '' ?>"><?= $t['kapsam'] === 'yaz' ? 'Okuma + yazma' : 'Yalnızca okuma' ?></span></td>
            <td><?= $t['son_kullanim'] ? Str::date($t['son_kullanim'], 'once') : '—' ?></td><td><?= Str::date($t['olusturma'], 'd M Y') ?></td>
            <td><form method="post"><?= bz_csrf_field() ?><input type="hidden" name="eylem" value="sil"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="baglanti tehlike-metin" data-onay="Anahtar iptal edilsin mi? Kullanan uygulamalar erişimi kaybeder.">İptal et</button></form></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <section class="kart">
      <h2>Kullanım</h2>
      <h3>REST API</h3>
      <pre class="kod">curl <?= e($site) ?>/api/v1/blog?limit=5 \
  -H "Authorization: Bearer bz_..."

curl -X POST <?= e($site) ?>/api/v1/blog \
  -H "Authorization: Bearer bz_..." -H "Content-Type: application/json" \
  -d '{"baslik":"Yeni yazı","durum":"taslak","alanlar":{"icerik":"&lt;p&gt;Merhaba&lt;/p&gt;"}}'</pre>
      <h3>MCP (Claude Desktop, Cursor, ChatGPT bağlayıcıları)</h3>
      <pre class="kod">{
  "mcpServers": {
    "<?= e(Str::slug(App::setting('site_adi', 'site'))) ?>": {
      "type": "http",
      "url": "<?= e($site) ?>/mcp",
      "headers": { "Authorization": "Bearer bz_..." }
    }
  }
}</pre>
      <p class="soluk kucuk-metin">MCP durumu: <strong><?= App::setting('mcp_acik') === '1' ? 'açık' : 'kapalı' ?></strong> (Ayarlar › Yapay Zekâ). Yazma anahtarıyla yapay zekâ yalnızca <em>taslak</em> oluşturabilir ve var olan içeriği güncelleyebilir; yayınlama her zaman sizdedir. Tüm işlemler etkinlik günlüğüne yazılır.</p>
    </section>
  </div>
  <aside class="duzen-yan">
    <form method="post" class="kart">
      <?= bz_csrf_field() ?><input type="hidden" name="eylem" value="olustur">
      <h3>Yeni anahtar</h3>
      <div class="alan"><label>Ad</label><input name="ad" placeholder="Örn: Mobil uygulama" required maxlength="120"></div>
      <div class="alan"><label>Kapsam</label><select name="kapsam"><option value="oku">Yalnızca okuma</option><option value="yaz">Okuma + yazma</option></select></div>
      <button class="dugme tam">Oluştur</button>
    </form>
  </aside>
</div>
