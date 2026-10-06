# BOZKURT Şablon Dili Rehberi

BOZKURT şablonları düz HTML dosyalarıdır. `sablonlar/` klasörüne koyduğunuz her `.html` dosyası (adı `_` ile başlamayan, `404.html` dışındaki) bir **şablon** ve bir **adres** olur:

| Dosya | Adres |
|---|---|
| `sablonlar/ana-sayfa.html` | `/` |
| `sablonlar/hakkimizda.html` | `/hakkimizda` |
| `sablonlar/blog.html` (coklu) | `/blog` (liste) ve `/blog/yazi-adi` (tekil) |
| `sablonlar/404.html` | Bulunamayan sayfalar |
| `sablonlar/_bakim.html` | Bakım modu sayfası (isteğe bağlı) |
| `sablonlar/parcalar/*.html` | Ortak parçalar (`<bz:dahil>` ile) |

Şablonlar ilk istekte PHP'ye derlenir ve `veri/onbellek/derlenmis/` altında saklanır; dosyayı değiştirdiğinizde otomatik yeniden derlenir.

---

## 1. Şablon ayarları — `<bz:sablon>`

```html
<bz:sablon baslik="Blog" coklu="evet" sira="3" ikon="kalem" menude="evet" schema="BlogPosting" />
```

| Nitelik | Açıklama | Varsayılan |
|---|---|---|
| `baslik` | Panelde ve menüde görünen ad | Dosya adından |
| `coklu` | `evet` ise blog/ürün gibi çok kayıtlı içerik | `hayir` |
| `sira` | Panel ve menü sırası | `50` |
| `menude` | `<bz:menu>` çıktısında gösterilsin mi | `evet` |
| `menu_adi` | Menüde farklı ad | `baslik` |
| `tekil_adi` | “Yeni …” düğmesinde kullanılacak ad (ör. `Yazı`) | `baslik` |
| `ikon` | Panel ikonu: `sayfa`, `yigin`, `ev`, `kalem`, `medya`, `form`, `dunya` | — |
| `sitemap` | `hayir` ise sitemap'e eklenmez | `evet` |
| `schema` | Tekil görünüm JSON-LD türü (`Article`, `Product`, `Event`…); tekil sayfada `FAQPage` → `soru`/`cevap` satırlarından FAQ şeması | `Article` |
| `noindex` | `evet` → bu şablonun tüm sayfaları arama motorlarına kapalı (ör. arama sonuçları) | `hayir` |
| `llms` | `hayir` → llms.txt'ye eklenmez | `evet` |

## 2. Düzenlenebilir alan — `<bz:alan>`

```html
<h1><bz:alan ad="baslik" etiket="Başlık" zorunlu="evet">Varsayılan başlık</bz:alan></h1>
<bz:alan ad="kapak" tur="resim" etiket="Kapak" goster="hayir" />
```

Etiket hem alanı **tanımlar** (panelde form öğesi oluşur) hem de bulunduğu yerde değerini **yazdırır**. Açılış ve kapanış arasındaki içerik varsayılan değerdir. `goster="hayir"` yalnızca tanımlar; değeri `{{ kapak }}` ile istediğiniz yerde kullanırsınız.

| Nitelik | Açıklama |
|---|---|
| `ad` | Küçük harf, rakam, `_` (ör. `alt_baslik`) — zorunlu |
| `tur` | Aşağıdaki tablodan; varsayılan `metin` |
| `etiket` | Panelde görünen ad |
| `yardim` | Alanın altında açıklama |
| `zorunlu` | `evet` |
| `grup` | Paneldeki kart başlığı (ör. `Karşılama`, `SEO`) |
| `genislik` | `yarim` → panelde yan yana iki alan |
| `secenekler` | `secim`/`coklusecim` için: `Kırmızı, Mavi` veya `Kırmızı=kirmizi, Mavi=mavi` |
| `sablon` | `iliski` türü için hedef şablon (ör. `kategoriler`) |
| `en_fazla` | En fazla karakter |
| `yer_tutucu` | Girdi içi ipucu |
| `aranabilir` | `hayir` → site aramasına dahil etme |
| `goster` | `hayir` → yazdırma |
| `api` | `hayir` → JSON API, MCP ve `.md` çıktılarında gösterme (iç notlar için) |
| `cevrilebilir` | `hayir` → YZ toplu çevirisinde atla |

### Alan türleri

| `tur` | Panelde | Şablonda çıktı |
|---|---|---|
| `metin` | Tek satır | Kaçışlı metin |
| `uzunmetin` | Çok satır | Satır sonları `<br>` |
| `zengin` | Editör (başlık, liste, bağlantı, resim, YouTube) | Temizlenmiş HTML (başlıklara otomatik `id`) |
| `resim` | Medya seçici | Resim URL'si |
| `dosya` | Medya seçici | Dosya URL'si |
| `tarih` / `tarihsaat` | Takvim | “07 Ekim 2026” |
| `sayi` | Sayı | Sayı |
| `fiyat` | ₺ önekli | “1.234,50 ₺” |
| `secim` | Açılır liste | Seçilen değer |
| `coklusecim` | Çipler | Virgülle birleşik |
| `onay` | Anahtar | `1` veya boş |
| `eposta` / `url` / `telefon` | Doğrulamalı girdi | Değer (telefon `0532 123 45 67` biçiminde) |
| `renk` | Renk seçici | `#b91c1c` |
| `iliski` | Başka şablondan kayıt seçimi | Kaydın başlığı; `{{ oge.kategori.url }}` gibi alt alanlar |
| `video` | YouTube/Vimeo bağlantısı | Duyarlı gömülü oynatıcı |
| `harita` | Adres | Google Haritalar gömmesi |
| `tekrar` | Satır ekle/sil/sırala | (bkz. `<bz:tekrar>`) |
| `bloklar` | Blok türüne göre ekle/sırala | (bkz. `<bz:bloklar>`) |
| `markdown` | Markdown metin alanı | Temizlenmiş HTML |
| `il` | 81 il açılır listesi | İl adı |
| `tckn` / `vkn` | Algoritmik doğrulamalı kimlik/vergi no | Değer |
| `iban` | Mod-97 doğrulamalı IBAN | 4'lü gruplanmış IBAN |

## 3. Tekrarlanan grup — `<bz:tekrar>`

```html
<bz:tekrar ad="sss" etiket="Sık sorulan sorular">
  <details>
    <summary><bz:alan ad="soru" etiket="Soru" /></summary>
    <bz:alan ad="cevap" tur="uzunmetin" etiket="Cevap" />
  </details>
</bz:tekrar>
```

İçerideki alanlar her satır için tekrarlanır. Satır değerlerine `{{ satir.soru }}` ile de ulaşabilirsiniz. Satır sayısı: `{{ sss | say }}`.

## 3b. Blok düzenleyici — `<bz:bloklar>`

Editörün sayfayı hazır bloklardan dilediği sırayla kurmasını sağlar. Her `<bz:blok>` bir blok türüdür ve yalnızca o türdeki satırlarda görünür:

```html
<bz:bloklar ad="govde" etiket="Sayfa blokları">
  <bz:blok ad="metin" etiket="Metin">
    <div class="metin"><bz:alan ad="icerik" tur="zengin" etiket="Metin" /></div>
  </bz:blok>
  <bz:blok ad="cagri" etiket="Eylem çağrısı">
    <a class="dugme" href="{{ satir.link }}"><bz:alan ad="buton" etiket="Buton" /></a>
    <bz:alan ad="link" tur="url" etiket="Bağlantı" goster="hayir" />
  </bz:blok>
</bz:bloklar>
```

## 4. Değişkenler ve süzgeçler

```
{{ degisken }}                kaçışlı çıktı (XSS güvenli)
{{{ degisken }}}              ham HTML (yalnızca zengin metin için)
{{ degisken | suzgec:"arg" | suzgec2 }}
```

**Hazır değişkenler**

| Değişken | Açıklama |
|---|---|
| `sayfa.*` veya doğrudan alan adı | Geçerli sayfanın alanları (`{{ baslik }}` = `{{ sayfa.baslik }}`) |
| `sayfa.url`, `sayfa.tam_url`, `sayfa.tarih`, `sayfa.guncelleme`, `sayfa.ozet`, `sayfa.okuma_suresi` | Sistem alanları |
| `gorunum` | `sayfa`, `liste` veya `tekil` |
| `oge.*`, `sira`, `ilk`, `son` | `<bz:liste>` içinde |
| `satir.*` | `<bz:tekrar>` içinde |
| `genel.telefon` … | Genel alanlar |
| `site.ad`, `site.slogan`, `site.aciklama`, `site.url`, `site.kok`, `site.ana`, `site.logo` | Site ayarları (`site.ana` = geçerli dilin ana sayfası; `site.onek` = dile göre bağlantı öneki, `/` veya `/en/` — iç bağlantılarda `{{ site.onek }}blog` kullanın) |
| `site.dil`, `site.diller`, `site.yon` | Geçerli dil, etkin diller, yazı yönü (`rtl` Arapça/Farsça) |
| `istek.q`, `istek.sayfa` | Arama terimi, sayfa numarası |
| `liste.toplam`, `liste.sayfa`, `liste.sayfa_sayisi` | Son listenin bilgileri |
| `form.basarili`, `form.hatalar`, `form.eski.alan` | Form durumu |
| `kullanici.ad` | Giriş yapmış yönetici |
| `yil`, `simdi` | Bu yıl, şu an |

**Süzgeçler**

| Süzgeç | Örnek | Sonuç |
|---|---|---|
| `tarih:"biçim"` | `{{ tarih \| tarih:"d F Y, l" }}` | 07 Ekim 2026, Çarşamba |
| `once` | `{{ tarih \| once }}` | 3 gün önce |
| `kisalt:n` / `ozet:n` | `{{ icerik \| kisalt:120 }}` | HTML'siz, kelimede kesilmiş özet |
| `buyuk` / `kucuk` / `baslik` | `{{ "istanbul" \| buyuk }}` | İSTANBUL (Türkçe İ/ı doğru) |
| `tl` | `{{ fiyat \| tl }}` | 1.234,50 ₺ |
| `sayi:ondalik` | `{{ adet \| sayi }}` | 12.500 |
| `resim:"GxY":"mod"` | `{{ kapak \| resim:"600x400" }}` | WebP küçük resim (`kirp` veya `sigdir`; `800x0` oran korur) |
| `varsayilan:"x"` | `{{ alt \| varsayilan:"—" }}` | Boşsa x |
| `satirlar` | `{{ adres \| satirlar }}` | Satır sonlarını `<br>` yapar |
| `telefon` / `tel_link` / `whatsapp` | `href="{{ genel.telefon \| whatsapp }}"` | `https://wa.me/90532…` |
| `url_kodla`, `slug`, `json`, `html_temizle` | | |
| `say`, `ilk`, `birlestir:", "` | Diziler için | |
| `okuma_suresi`, `video` | | |
| `cevir` | `{{ "Devamını oku" \| cevir }}` | `sablonlar/diller/en.json` sözlüğünden geçerli dile çeviri |
| `resimler:"480,800"` | `srcset="{{ kapak \| resimler:"480,800,1200" }}"` | Duyarlı srcset dizesi |
| `markdown` | `{{{ notlar \| markdown }}}` | Markdown → HTML |
| `tatil` | `{{ tarih \| tatil }}` | Resmî tatilse adı, değilse boş |

## 5. Koşullar — `<bz:eger>`

```html
<bz:eger kosul="gorunum == 'tekil'"> … 
<bz:yoksa-eger kosul="oge.fiyat > 1000 ve oge.stokta"> …
<bz:degilse/> …
</bz:eger>
```

Operatörler: `==`, `!=`, `>`, `<`, `>=`, `<=`, `icerir`; bağlaçlar `ve` / `&&`, `veya` / `||`; olumsuzlama `!alan` veya `degil alan`. Tek başına alan adı “dolu mu?” demektir.

## 6. Listeler — `<bz:liste>`

```html
<bz:liste sablon="blog" limit="6" sirala="tarih" yon="azalan" sayfalama="evet" filtre="kategori=Haber">
  <article>{{ sira }}. <a href="{{ oge.url }}">{{ oge.baslik }}</a></article>
<bz:yoksa/>
  <p>Kayıt yok.</p>
</bz:liste>
<bz:sayfalama />
```

| Nitelik | Açıklama |
|---|---|
| `sablon` | Kaynak şablon; boşsa geçerli şablon; `*` tüm çoklu şablonlar (arama için) |
| `limit` | Sayfa başına kayıt (en fazla 500) |
| `sirala` | `tarih`, `baslik`, `sira`, `guncelleme`, `rastgele` veya herhangi bir alan adı |
| `yon` | `azalan` / `artan` |
| `sayfalama` | `evet` → `?sayfa=2` desteği |
| `filtre` | `alan=deger;alan2!=deger` (ilişki alanında slug veya id) |
| `url_filtre` | `kategori` → `?kategori=Haber` adres parametresiyle süzme |
| `arama` | `evet` → `?q=` ile tam metin arama |
| `haric_bu` | `evet` → geçerli kaydı listeleme |
| `arsiv` | `evet` → `?yil=2026&ay=10` adres parametreleriyle tarih arşivi |
| `dil` | Başka dildeki kayıtları listele (varsayılan: geçerli dil) |
| `ilgili` | `kategori` → geçerli kayıtla aynı kategoridekiler (benzer yazılar) |

## 7. Diğer etiketler

| Etiket | Ne yapar |
|---|---|
| `<bz:dahil dosya="parcalar/ust" />` | Parça dosyasını ekler |
| `<bz:genel ad="telefon" tur="telefon" etiket="Telefon" grup="İletişim" />` | Site geneli alan (Panel › Genel Alanlar) |
| `<bz:seo />` | `<title>`, meta, Open Graph, canonical, JSON-LD, doğrulama kodları, favicon, RSS bağlantısı |
| `<bz:menu sinif="menu" />` | Şablonlardan otomatik menü (`aktif` sınıfıyla) |
| `<bz:ekmek-kirintisi />` | Konum yolu + BreadcrumbList şeması |
| `<bz:arama-formu />` | `/ara` sayfasına arama formu |
| `<bz:icindekiler alan="icerik" />` | Zengin metnin H2/H3 başlıklarından içindekiler |
| `<bz:sayfalama />` | Son listenin sayfa bağlantıları |
| `<bz:kvkk-bandi />` | Çerez onay bandı (analitik betikleri onaya bağlar) |
| `<bz:analitik />` | GA4 / Yandex Metrica (ayarlardan) |
| `<bz:ayarla ad="x" deger="y" />` | Şablon değişkeni tanımlar |
| `<bz:dil-secici gorunum="ad" />` | Geçerli sayfanın diğer dillerdeki karşılıklarına bağlantılar |
| `<bz:kategoriler sablon="blog" alan="kategori" />` | Kategori listesi ve içerik sayıları (`?kategori=` bağlantılarıyla) |
| `<bz:arsiv sablon="blog" />` | Ay/yıl arşivi |
| `<bz:resim alan="kapak" boyutlar="480,800,1200" sizes="…" yukleme="eager" />` | srcset'li, WebP, tembel yüklenen `<img>` |
| `<bz:odeme alan="fiyat" />` | PayTR/iyzico ödeme formu (tutar sunucuda içerikten okunur) |
| `<bz:iys-onay kanallar="E-posta,SMS" />` | İYS ticari ileti onay kutusu (formun içinde) |

## 8. Formlar — `<bz:form>`

```html
<bz:form ad="teklif" zorunlu="ad_soyad,eposta" konu="Yeni teklif talebi" basari="Talebiniz alındı!" alici="satis@firma.com.tr">
  <input name="ad_soyad" value="{{ form.eski.ad_soyad }}" required>
  <input name="eposta" type="email" required>
  <textarea name="mesaj"></textarea>
  <bz:kvkk-onay />
  <button>Gönder</button>
</bz:form>
```

- Gönderimler **Panel › Form Mesajları**'nda listelenir, CSV olarak (Excel uyumlu, Türkçe karakterli) indirilir.
- Spam koruması: gizli bal küpü alanı, imzalı zaman belirteci (3 saniyeden hızlı gönderimler reddedilir), köken denetimi, IP başına 10 dakikada 5 gönderim sınırı. Oturum açmadığı için tam sayfa önbellekle uyumludur.
- `kvkk="hayir"` ile onay zorunluluğunu kaldırabilir, `bildirim="hayir"` ile e-postayı kapatabilirsiniz.
- `<bz:kvkk-onay metin="…" />` ile onay metnini özelleştirebilirsiniz.

## 9. İpuçları

- Görselleri her zaman `| resim:"GxY"` ile kullanın: WebP üretilir, sayfa hızlanır.
- `{{{ }}}` yalnızca `zengin` alanlar içindir; diğer her şeyde `{{ }}` kullanın.
- Şablonda hata varsa `veri/yapilandirma.php` içinde `'hata_ayiklama' => true` yapın (yayında kapatın).

## 10. Çok dilli siteler

1. Ayarlar › Diller: `tr,en` (ilki varsayılan). İngilizce sayfalar `/en/…` adresinde yayınlanır.
2. Panelde içeriği açın, üstteki **+ English** sekmesiyle çeviri oluşturun (✨ ile tek tıkla YZ çevirisi).
3. Genel alanları her dil için ayrı doldurun; boş bırakılan alanlar varsayılan dile düşer.
4. Şablon metinleri: `sablonlar/diller/en.json` + `{{ "Metin" | cevir }}`.
5. `hreflang`, `x-default`, sitemap alternatifleri, `<html lang>` ve `og:locale` otomatik üretilir.

## 11. Satır içi düzenleme

Giriş yapmışken ön yüzde **✍ Sayfada düzenle**'ye tıklayın; `<bz:alan>` ile basılan metin ve zengin metin alanları sayfada düzenlenebilir hâle gelir, odak dışına çıkınca kaydedilir.
