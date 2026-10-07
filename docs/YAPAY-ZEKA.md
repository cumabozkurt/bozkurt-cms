# Yapay Zekâ Katmanı

BOZKURT CMS, yapay zekâyı hem **editörün yardımcısı** hem de sitenizin **yapay zekâ asistanlarında görünürlüğü** için kullanır. Hiçbiri zorunlu değildir; anahtar eklemeden de site çalışır.

## 1. Editör yardımcısı (Ayarlar › Yapay Zekâ)

OpenAI uyumlu her uç nokta: OpenAI, OpenRouter, Groq, Mistral, DeepSeek, Google Gemini (OpenAI uyumlu uç), Azure OpenAI, yerel **Ollama / LM Studio** (`http://localhost:11434/v1`). Anahtar yalnızca sunucuda saklanır.

| Nerede | Ne yapar |
|---|---|
| Zengin metin editörü › ✨ YZ | Yazım düzeltme, özet, 5 başlık önerisi, serbest komut (“daha samimi yap”, “sonuna eylem çağrısı ekle”) |
| SEO kartı › ✨ | 60 karakterlik başlık + 140–158 karakterlik meta açıklama |
| Çeviri ekranı › ✨ Tüm alanları çevir | HTML yapısını, bağlantıları ve marka adlarını koruyarak tüm metin alanlarını hedef dile çevirir |
| Medya › ✨ | Görselden ekran okuyucu için alt metin (görüntü destekli model) |

Güvenlik: YZ çıktısı panelde gösterilmeden önce istemci tarafında, kaydedilirken sunucu tarafında temizlenir (prompt enjeksiyonu → XSS engellenir). Marka üslubu ayarlanabilir; model uydurma bilgi eklememesi yönünde yönlendirilir.

## 2. Yanıt motorlarında görünürlük (GEO / AEO)

| Çıktı | Adres | Amaç |
|---|---|---|
| llms.txt | `/llms.txt` | llmstxt.org standardı: site özeti ve içerik haritası (her sayfanın `.md` bağlantısıyla) |
| llms-full.txt | `/llms-full.txt` | Tüm yayımlanmış içerik tek Markdown dosyada (400 KB sınırı) |
| Markdown sürüm | `/blog/yazi.md`, `/hakkimizda.md`, `/index.md` | Her sayfanın temiz metin sürümü; HTML'de `<link rel="alternate" type="text/markdown">` |
| robots.txt politikası | `/robots.txt` | Varsayılan: **eğitim botlarını engelle** (GPTBot, ClaudeBot, CCBot, Google-Extended…), **arama/yanıt botlarına izin ver** (OAI-SearchBot, ChatGPT-User, PerplexityBot, Claude-SearchBot…) |
| Yapılandırılmış veri | Her sayfa | WebSite + SearchAction, Article/BlogPosting, BreadcrumbList, FAQPage, LocalBusiness |

## 3. MCP sunucusu (Model Context Protocol)

`/mcp` adresinde Streamable HTTP (JSON-RPC 2.0, protokol 2025-06-18). Claude Desktop, Cursor, ChatGPT bağlayıcıları ve diğer MCP istemcileri sitenize bağlanabilir.

1. Ayarlar › Yapay Zekâ › **MCP sunucusunu aç**
2. API ve MCP › **Yeni anahtar** (yalnızca okuma veya okuma + yazma)
3. İstemci yapılandırması:

```json
{
  "mcpServers": {
    "sitem": {
      "type": "http",
      "url": "https://alanadiniz.com/mcp",
      "headers": { "Authorization": "Bearer bz_..." }
    }
  }
}
```

Araçlar: `sablonlari_listele`, `icerik_listele`, `icerik_getir`, `ara`; yazma anahtarıyla `taslak_olustur` (her zaman **taslak**, yayın insan onayıyla) ve `icerik_guncelle` (önceki hâl sürüm geçmişine kaydedilir). Tüm işlemler etkinlik günlüğüne yazılır.

## 4. REST API (headless)

```
GET    /api/v1/sablonlar
GET    /api/v1/{sablon}?limit=10&sayfa=1&sirala=tarih&yon=azalan&q=&filtre=kategori=Haber&dil=en
GET    /api/v1/{sablon}/{slug}
POST   /api/v1/{sablon}            {"baslik":"…","durum":"taslak","alanlar":{…},"dil":"tr"}
PATCH  /api/v1/{sablon}/{slug}     (gönderilmeyen alanlar korunur)
DELETE /api/v1/{sablon}/{slug}
```

Okuma herkese açık yapılabilir veya anahtar ister; yazma her zaman `yaz` kapsamlı anahtar ister. Dakikada 120 istek sınırı. `api="hayir"` alanlar hiçbir dış çıktıda yer almaz.

## 5. Web kancaları

`icerik.eklendi`, `icerik.guncellendi`, `icerik.silindi`, `form.gonderildi`, `siparis.odendi` olayları JSON olarak gönderilir; `X-Bozkurt-Imza: sha256=…` (HMAC) ile doğrulayın. Özel ağ adreslerine gönderim engellidir.
