# Template language

BOZKURT templates are plain HTML files. Every `.html` file you put in `sablonlar/` (except `404.html`
and files whose name starts with `_`) becomes a **template** (content type) and a **URL**.
Tag and attribute names are Turkish; English meanings are given below. The complete Turkish reference is
[SABLON-REHBERI.md](../SABLON-REHBERI.md).

| File | URL |
|---|---|
| `sablonlar/ana-sayfa.html` | `/` |
| `sablonlar/hakkimizda.html` | `/hakkimizda` |
| `sablonlar/blog.html` (multi-entry) | `/blog` (list) and `/blog/post-slug` (single entry) |
| `sablonlar/404.html` | Not-found page |
| `sablonlar/_bakim.html` | Maintenance-mode page (optional) |
| `sablonlar/parcalar/*.html` | Shared partials (included with `<bz:dahil>`) |

Templates are compiled to PHP on first request and stored in `veri/onbellek/derlenmis/`; editing a file
triggers recompilation. Raw PHP in a template is stripped and never executed.

Boolean attributes use `evet` (yes) and `hayir` (no).

---

## 1. Template settings — `<bz:sablon>`

```html
<bz:sablon baslik="Blog" coklu="evet" sira="3" ikon="kalem" menude="evet" schema="BlogPosting" />
```

| Attribute | Meaning | Default |
|---|---|---|
| `baslik` | Name shown in the panel and menu | From the file name |
| `coklu` | `evet` = multi-entry content (blog, products …) | `hayir` |
| `sira` | Order in panel and menu | `50` |
| `menude` | Include in `<bz:menu>` output | `evet` |
| `menu_adi` | Different name in the menu | `baslik` |
| `tekil_adi` | Singular name for the "New …" button (e.g. `Yazı`) | `baslik` |
| `ikon` | Panel icon: `sayfa`, `yigin`, `ev`, `kalem`, `medya`, `form`, `dunya` | — |
| `sitemap` | `hayir` = exclude from the sitemap | `evet` |
| `schema` | JSON-LD type for single views (`Article`, `Product`, `Event` …). On a single page, `FAQPage` builds FAQ schema from `soru`/`cevap` repeater rows. | `Article` |
| `noindex` | `evet` = all pages of this template are `noindex` (e.g. search results) | `hayir` |
| `llms` | `hayir` = exclude from `llms.txt` | `evet` |

## 2. Editable field — `<bz:alan>`

```html
<h1><bz:alan ad="baslik" etiket="Başlık" zorunlu="evet">Default title</bz:alan></h1>
<bz:alan ad="kapak" tur="resim" etiket="Cover" goster="hayir" />
```

The tag both **defines** a field (a form control appears in the panel) and **prints** its value where it
stands. Content between the opening and closing tag is the default value. `goster="hayir"` only defines the
field; print it anywhere with `{{ kapak }}`.

| Attribute | Meaning |
|---|---|
| `ad` | Name: lower-case letters, digits, `_` (e.g. `alt_baslik`) — required |
| `tur` | Field type (table below); default `metin` |
| `etiket` | Label shown in the panel |
| `yardim` | Help text under the field |
| `zorunlu` | `evet` = required |
| `grup` | Card heading in the panel (e.g. `Hero`, `SEO`) |
| `genislik` | `yarim` = half width (two fields side by side) |
| `secenekler` | Options for `secim`/`coklusecim`: `Red, Blue` or `Red=red, Blue=blue` |
| `sablon` | Target template for `iliski` (relation) fields |
| `en_fazla` | Maximum characters |
| `yer_tutucu` | Placeholder |
| `aranabilir` | `hayir` = exclude from site search |
| `goster` | `hayir` = do not print |
| `api` | `hayir` = hide from the JSON API, MCP and `.md` output (internal notes) |
| `cevrilebilir` | `hayir` = skip in AI bulk translation |

### Field types (`tur`)

| Type | Panel control | Template output |
|---|---|---|
| `metin` (text) | Single line | Escaped text |
| `uzunmetin` (long text) | Textarea | Line breaks → `<br>` |
| `zengin` (rich text) | Editor (headings, lists, links, images, YouTube) | Sanitized HTML (headings get automatic `id`s) |
| `resim` (image) | Media picker | Image URL |
| `dosya` (file) | Media picker | File URL |
| `tarih` / `tarihsaat` (date / datetime) | Date picker | "07 Ekim 2026" |
| `sayi` (number) | Number | Number |
| `fiyat` (price) | ₺-prefixed input | "1.234,50 ₺" |
| `secim` (select) | Drop-down | Selected value |
| `coklusecim` (multi-select) | Chips | Comma-joined values |
| `onay` (checkbox) | Toggle | `1` or empty |
| `eposta` / `url` / `telefon` | Validated input | Value (phone formatted as `0532 123 45 67`) |
| `renk` (colour) | Colour picker | `#b91c1c` |
| `iliski` (relation) | Pick an entry from another template | Entry title; sub-fields like `{{ oge.kategori.url }}` |
| `video` | YouTube/Vimeo URL | Responsive embedded player |
| `harita` (map) | Address | Google Maps embed |
| `tekrar` (repeater) | Add/remove/reorder rows | See `<bz:tekrar>` |
| `bloklar` (blocks) | Add/reorder blocks by type | See `<bz:bloklar>` |
| `markdown` | Markdown textarea | Sanitized HTML |
| `il` (province) | Drop-down of Turkey's 81 provinces | Province name |
| `tckn` / `vkn` | National ID / tax number with checksum validation | Value |
| `iban` | IBAN with mod-97 validation | IBAN grouped by 4 |

## 3. Repeater — `<bz:tekrar>`

```html
<bz:tekrar ad="sss" etiket="FAQ">
  <details>
    <summary><bz:alan ad="soru" etiket="Question" /></summary>
    <bz:alan ad="cevap" tur="uzunmetin" etiket="Answer" />
  </details>
</bz:tekrar>
```

Fields inside are repeated for every row. Row values are also available as `{{ satir.soru }}`. Row count:
`{{ sss | say }}`.

## 3b. Block editor — `<bz:bloklar>`

Lets editors build a page from predefined blocks in any order. Each `<bz:blok>` is a block type and renders
only for rows of that type:

```html
<bz:bloklar ad="govde" etiket="Page blocks">
  <bz:blok ad="metin" etiket="Text">
    <div class="text"><bz:alan ad="icerik" tur="zengin" etiket="Text" /></div>
  </bz:blok>
  <bz:blok ad="cagri" etiket="Call to action">
    <a class="button" href="{{ satir.link }}"><bz:alan ad="buton" etiket="Button" /></a>
    <bz:alan ad="link" tur="url" etiket="Link" goster="hayir" />
  </bz:blok>
</bz:bloklar>
```

## 4. Variables and filters

```
{{ variable }}                 escaped output (XSS-safe)
{{{ variable }}}               raw HTML (only for rich-text fields, which are sanitized on save)
{{ variable | filter:"arg" | filter2 }}
```

**Built-in variables**

| Variable | Meaning |
|---|---|
| `sayfa.*` or a bare field name | Fields of the current page (`{{ baslik }}` = `{{ sayfa.baslik }}`) |
| `sayfa.url`, `sayfa.tam_url`, `sayfa.tarih`, `sayfa.guncelleme`, `sayfa.ozet`, `sayfa.okuma_suresi` | System fields: URL, absolute URL, date, last update, excerpt, reading time |
| `gorunum` | View: `sayfa` (page), `liste` (list) or `tekil` (single entry) |
| `oge.*`, `sira`, `ilk`, `son` | Inside `<bz:liste>`: item, index, first, last |
| `satir.*` | Inside `<bz:tekrar>`: row |
| `genel.telefon` … | Global fields |
| `site.ad`, `site.slogan`, `site.aciklama`, `site.url`, `site.kok`, `site.ana`, `site.logo` | Site settings. `site.ana` = home page of the current language. `site.onek` = language-aware link prefix (`/` or `/en/`) — write internal links as `{{ site.onek }}blog`. |
| `site.dil`, `site.diller`, `site.yon` | Current language, enabled languages, text direction (`rtl` for Arabic/Persian) |
| `istek.q`, `istek.sayfa` | Search query, page number |
| `liste.toplam`, `liste.sayfa`, `liste.sayfa_sayisi` | Info about the last list: total, page, page count |
| `form.basarili`, `form.hatalar`, `form.eski.field` | Form state: success, errors, previous input |
| `kullanici.ad` | Logged-in admin user |
| `yil`, `simdi` | Current year, now |

**Filters**

| Filter | Example | Result |
|---|---|---|
| `tarih:"format"` | `{{ tarih \| tarih:"d F Y, l" }}` | 07 Ekim 2026, Çarşamba (Turkish month/day names) |
| `once` | `{{ tarih \| once }}` | "3 gün önce" (3 days ago) |
| `kisalt:n` / `ozet:n` | `{{ icerik \| kisalt:120 }}` | HTML-free excerpt cut at a word boundary |
| `buyuk` / `kucuk` / `baslik` | `{{ "istanbul" \| buyuk }}` | İSTANBUL (correct Turkish İ/ı casing) |
| `tl` | `{{ fiyat \| tl }}` | 1.234,50 ₺ |
| `sayi:decimals` | `{{ adet \| sayi }}` | 12.500 |
| `resim:"WxH":"mode"` | `{{ kapak \| resim:"600x400" }}` | WebP thumbnail (`kirp` = crop or `sigdir` = fit; `800x0` keeps the ratio) |
| `varsayilan:"x"` | `{{ alt \| varsayilan:"—" }}` | x when empty |
| `satirlar` | `{{ adres \| satirlar }}` | Line breaks → `<br>` |
| `telefon` / `tel_link` / `whatsapp` | `href="{{ genel.telefon \| whatsapp }}"` | `https://wa.me/90532…` |
| `url_kodla`, `slug`, `json`, `html_temizle` | | URL-encode, slugify, JSON-encode, strip HTML |
| `say`, `ilk`, `birlestir:", "` | For arrays | Count, first item, join |
| `okuma_suresi`, `video` | | Reading time; video URL → embed |
| `cevir` | `{{ "Devamını oku" \| cevir }}` | Translates using `sablonlar/diller/<lang>.json` |
| `resimler:"480,800"` | `srcset="{{ kapak \| resimler:"480,800,1200" }}"` | Responsive `srcset` string |
| `markdown` | `{{{ notlar \| markdown }}}` | Markdown → sanitized HTML |
| `tatil` | `{{ tarih \| tatil }}` | Name of the Turkish public holiday on that date, or empty |
| `md5` | `{{ oge.eposta \| md5 }}` | MD5 hash (e.g. for Gravatar URLs) |

## 5. Conditions — `<bz:eger>`

```html
<bz:eger kosul="gorunum == 'tekil'"> …
<bz:yoksa-eger kosul="oge.fiyat > 1000 ve oge.stokta"> …
<bz:degilse/> …
</bz:eger>
```

`eger` = if, `yoksa-eger` = else-if, `degilse` = else. Operators: `==`, `!=`, `>`, `<`, `>=`, `<=`,
`icerir` (contains); conjunctions `ve` / `&&` (and), `veya` / `||` (or); negation `!field` or
`degil field`. A bare field name means "is not empty".

`<bz:degilse/>` and `<bz:yoksa-eger>` are only valid directly inside `<bz:eger>`; the linter reports them
elsewhere.

## 6. Lists — `<bz:liste>`

```html
<bz:liste sablon="blog" limit="6" sirala="tarih" yon="azalan" sayfalama="evet" filtre="kategori=Haber">
  <article>{{ sira }}. <a href="{{ oge.url }}">{{ oge.baslik }}</a></article>
<bz:yoksa/>
  <p>No entries.</p>
</bz:liste>
<bz:sayfalama />
```

`<bz:yoksa/>` (inside a list only) starts the empty-state content.

| Attribute | Meaning |
|---|---|
| `sablon` | Source template; empty = current template; `*` = all multi-entry templates (for search) |
| `limit` | Items per page (max 500) |
| `sirala` | Sort by `tarih`, `baslik`, `sira`, `guncelleme`, `rastgele` (random) or any field name |
| `yon` | `azalan` (descending) / `artan` (ascending) |
| `sayfalama` | `evet` = pagination via `?sayfa=2` |
| `filtre` | `field=value;field2!=value` (relation fields accept slug or id) |
| `url_filtre` | e.g. `kategori` = filter by the `?kategori=News` query parameter |
| `arama` | `evet` = full-text search via `?q=` |
| `haric_bu` | `evet` = exclude the current entry |
| `arsiv` | `evet` = date archive via `?yil=2026&ay=10` |
| `dil` | List entries of another language (default: current language) |
| `ilgili` | e.g. `kategori` = entries sharing the current entry's category (related posts) |

## 7. Other tags

| Tag | What it does |
|---|---|
| `<bz:dahil dosya="parcalar/ust" />` | Includes a partial |
| `<bz:genel ad="telefon" tur="telefon" etiket="Phone" grup="Contact" />` | Site-wide field (Panel › Genel Alanlar) |
| `<bz:seo />` | `<title>`, meta, Open Graph, canonical, JSON-LD, verification codes, favicon, RSS link |
| `<bz:menu sinif="menu" />` | Automatic menu from templates (`aktif` class on the current item) |
| `<bz:ekmek-kirintisi />` | Breadcrumb + BreadcrumbList schema |
| `<bz:arama-formu />` | Search form pointing to `/ara` |
| `<bz:icindekiler alan="icerik" />` | Table of contents from the H2/H3 headings of a rich-text field |
| `<bz:sayfalama />` | Pagination links for the last list |
| `<bz:kvkk-bandi />` | Cookie consent banner (analytics scripts wait for consent) |
| `<bz:analitik />` | GA4 / Yandex Metrica / Meta Pixel from settings |
| `<bz:ayarla ad="x" deger="y" />` | Sets a template variable |
| `<bz:dil-secici gorunum="ad" />` | Links to the current page in other languages |
| `<bz:kategoriler sablon="blog" alan="kategori" />` | Category list with counts (`?kategori=` links) |
| `<bz:arsiv sablon="blog" />` | Month/year archive |
| `<bz:resim alan="kapak" boyutlar="480,800,1200" sizes="…" yukleme="eager" />` | `<img>` with `srcset`, WebP and lazy loading |
| `<bz:odeme alan="fiyat" />` | PayTR/iyzico payment form (the amount is read server-side from the content) |
| `<bz:iys-onay kanallar="E-posta,SMS" />` | İYS commercial-message consent checkbox (inside a form) |

## 8. Forms — `<bz:form>`

```html
<bz:form ad="teklif" zorunlu="ad_soyad,eposta" konu="New quote request" basari="Thank you!" alici="sales@example.com">
  <input name="ad_soyad" value="{{ form.eski.ad_soyad }}" required>
  <input name="eposta" type="email" required>
  <textarea name="mesaj"></textarea>
  <bz:kvkk-onay />
  <button>Send</button>
</bz:form>
```

- `ad` = form name, `zorunlu` = required fields, `konu` = e-mail subject, `basari` = success message,
  `alici` = recipient.
- Submissions are listed under **Panel › Form Mesajları** and can be downloaded as Excel-compatible CSV.
- Spam protection: hidden honeypot field, signed time token (submissions faster than 3 seconds are rejected),
  origin check, and a limit of 5 submissions per IP per 10 minutes. No session is needed, so forms work with
  the full-page cache.
- `kvkk="hayir"` removes the mandatory consent checkbox; `bildirim="hayir"` disables the notification e-mail.
- `<bz:kvkk-onay metin="…" />` customises the consent text; `{link}…{/link}` marks the link to the privacy notice.

## 9. Tips

- Always use images through `| resim:"WxH"`: WebP thumbnails are generated and pages load faster.
- Use `{{{ }}}` only for `zengin`/`markdown` output; use `{{ }}` for everything else.
- To debug a template, set `'hata_ayiklama' => true` in `veri/yapilandirma.php` (turn it off in production).

## 10. Multilingual sites

1. Settings › Diller: `tr,en` (the first is the default). English pages are served under `/en/…`.
2. Open an entry in the panel and use the **+ English** tab to create a translation (✨ for one-click AI translation).
3. Fill in global fields per language; empty fields fall back to the default language.
4. Template strings: `sablonlar/diller/en.json` + `{{ "Text" | cevir }}`.
5. `hreflang`, `x-default`, sitemap alternates, `<html lang>` and `og:locale` are generated automatically.

## 11. Inline editing

While logged in, click **✍ Sayfada düzenle** (Edit on page) on the front end. Text and rich-text fields
printed with `<bz:alan>` become editable in place and are saved when they lose focus. Authors' inline edits
follow the same publishing rules as the panel editor.
