# REST API, MCP server, webhooks and AI outputs

BOZKURT can act as a headless CMS (JSON API), expose content to AI clients (MCP server, `llms.txt`,
Markdown page versions) and notify other systems (webhooks). Everything is off or read-only by default
and controlled from the panel. JSON keys and messages are Turkish; translations are given below.

## API keys

Create keys in **Yönetim › API ve MCP**. Each key has a name and a scope:

| Scope | Allows |
|---|---|
| `oku` (read) | Reading the API when public read access is disabled; MCP read tools. |
| `yaz` (write) | Everything `oku` allows, plus API writes and MCP write tools. |

The key (`bz_…`) is shown **once**; only its SHA-256 hash is stored. Send it as:

```
Authorization: Bearer bz_xxxxxxxxxxxxxxxx
```

(`?anahtar=bz_…` in the query string is also accepted, but the header is preferred because URLs end up in logs.)

> On some Apache/LiteSpeed hosts PHP does not receive the `Authorization` header. The bundled `.htaccess`
> forwards it; the Nginx example includes `fastcgi_param HTTP_AUTHORIZATION`.

## REST API — `/api/v1`

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/v1/sablonlar` | Templates (content types) with their API-visible fields, plus enabled languages. |
| `GET` | `/api/v1/genel` | Global field values. |
| `GET` | `/api/v1/{template}` | Multi-entry: paginated list. Single-page template: that page. |
| `GET` | `/api/v1/{template}/{slug}` | One published entry. |
| `POST` | `/api/v1/{template}` | Create an entry (`yaz` key). |
| `PATCH` | `/api/v1/{template}/{slug}` | Update an entry; fields you do not send are kept (`yaz` key). For single-page templates use `PATCH /api/v1/{template}`. |
| `DELETE` | `/api/v1/{template}/{slug}` | Delete an entry (`yaz` key). Single pages cannot be deleted. |

**Read access**: public if **Ayarlar › Gelişmiş › JSON API okumayı herkese aç** is enabled, otherwise any
valid key is required. **Writes** always need a `yaz` key.

**List query parameters**

| Parameter | Meaning | Default |
|---|---|---|
| `limit` | Items per page (1–100) | `20` |
| `sayfa` | Page number | `1` |
| `sirala` | Sort field (`tarih`, `baslik`, `sira`, `guncelleme`, any field) | `tarih` |
| `yon` | `azalan` (desc) / `artan` (asc) | `azalan` |
| `q` | Full-text search (max 100 chars) | — |
| `filtre` | `field=value;field2!=value` | — |
| `dil` | Language code, must be enabled | default language |

**Responses**

```jsonc
// GET /api/v1/blog?limit=2
{
  "veri": [ { "baslik": "…", "slug": "…", "url": "…", "...": "fields" } ],
  "meta": { "toplam": 4, "sayfa": 1, "sayfa_sayisi": 2, "dil": "tr" }
}
```

- `veri` = data, `meta.toplam` = total, `sayfa_sayisi` = page count.
- Upload paths are returned as absolute URLs.
- Fields declared with `api="hayir"` are never included.
- Errors: `{"hata": "message"}` with status 400/401/403/404/405/429; validation errors return 422 with
  `{"hata": "Doğrulama hatası", "hatalar": {...}}`.

**Writing**

```bash
curl -X POST https://example.com/api/v1/blog \
  -H "Authorization: Bearer bz_…" -H "Content-Type: application/json" \
  -d '{"baslik":"Hello","durum":"taslak","dil":"tr","alanlar":{"icerik":"<p>Text</p>"}}'
```

Body keys: `baslik` (title), `slug`, `durum` (`taslak` draft / `yayinda` published), `yayin_tarihi`
(publish date), `sira` (order), `dil` (language), `alanlar` (field values). Values go through the same
validation and HTML sanitizer as the panel. Success: `201` (create) or `200` (update) with
`{"ok": true, "veri": {...}}`. Each write is recorded in the activity log.

**Limits and CORS**: 120 requests per minute per key/IP (then `429`). Set **CORS izinli köken** (allowed
origin) in Settings › Gelişmiş to call the API from a browser on another origin.

## MCP server — `/mcp`

A [Model Context Protocol](https://modelcontextprotocol.io/) server over Streamable HTTP (JSON-RPC 2.0,
protocol version `2025-06-18`), so AI clients that support remote MCP servers can read your content.

1. Settings › Yapay Zekâ › **MCP sunucusunu aç** (enable).
2. API ve MCP › create a key (`oku` or `yaz`).
3. Configure your client, for example:

```json
{
  "mcpServers": {
    "my-site": {
      "type": "http",
      "url": "https://example.com/mcp",
      "headers": { "Authorization": "Bearer bz_..." }
    }
  }
}
```

Only `POST` is accepted. Supported methods: `initialize`, `ping`, `tools/list`, `tools/call`,
`resources/list`, `resources/read` (exposes `llms.txt`); batches of up to 20 requests.

| Tool | Scope | What it does |
|---|---|---|
| `sablonlari_listele` | read | List templates, their fields and languages. |
| `icerik_listele` | read | List published entries of a template (`sablon`, `limit`, `sayfa`, `dil`, `filtre`). |
| `icerik_getir` | read | Get one entry with all API-visible fields (`sablon`, `slug`, `dil`). |
| `ara` | read | Full-text search across the site (`sorgu`, `dil`). |
| `taslak_olustur` | write | Create a new entry — always as a **draft**; a human publishes it. |
| `icerik_guncelle` | write | Update fields of an existing entry; status is unchanged and the previous version is saved as a revision. |

## Webhooks

List up to 5 target URLs (one per line) in **Ayarlar › Gelişmiş › Web kancası adresleri**. Events:

| Event | Sent when |
|---|---|
| `icerik.eklendi` | Content created |
| `icerik.guncellendi` | Content updated |
| `icerik.silindi` | Content deleted |
| `form.gonderildi` | A form was submitted |
| `siparis.odendi` | An order was paid |

Request: `POST` with `Content-Type: application/json` and headers `X-Bozkurt-Olay: <event>` and
`X-Bozkurt-Imza: sha256=<hex>`. Body:

```json
{ "olay": "icerik.eklendi", "zaman": "2026-10-07T12:00:00+03:00", "site": "https://example.com", "veri": { } }
```

The signature is an HMAC-SHA256 of the raw body using the **Web kancası imza anahtarı** (webhook secret),
or the site secret if none is set. Verify it before trusting the payload:

```php
$expected = 'sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $secret);
if (!hash_equals($expected, $_SERVER['HTTP_X_BOZKURT_IMZA'] ?? '')) { http_response_code(401); exit; }
```

Only public `http(s)` addresses are allowed; private, loopback and reserved IP ranges are refused, and the
connection is pinned to the validated IP to prevent DNS-rebinding.

## AI-facing outputs

| Output | URL | Purpose |
|---|---|---|
| `llms.txt` | `/llms.txt` | Site summary and content map in the llmstxt.org format, linking each page's `.md` version. |
| `llms-full.txt` | `/llms-full.txt` | All published content in one Markdown file (capped at 400 KB). |
| Markdown versions | `/blog/post.md`, `/hakkimizda.md`, `/index.md` | Clean-text version of each page; HTML pages link it with `<link rel="alternate" type="text/markdown">`. |
| `robots.txt` policy | `/robots.txt` | Default: block AI training crawlers, allow AI search/answer crawlers. Configurable. |

Each can be switched off under Settings › Yapay Zekâ. Turkish details: [YAPAY-ZEKA.md](../YAPAY-ZEKA.md).

## AI assistant (panel)

Configure any OpenAI-compatible endpoint (URL, key, model) under Settings › Yapay Zekâ. Remote endpoints
must use HTTPS and resolve to a public address; local endpoints such as Ollama (`http://localhost:11434/v1`)
are supported. The key is stored only on the server. Assistant output is sanitized before display and
again when saved.
