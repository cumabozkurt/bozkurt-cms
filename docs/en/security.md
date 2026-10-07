# Security model

This page summarises the protections built into BOZKURT CMS. Turkish version: [GUVENLIK.md](../GUVENLIK.md).
To report a vulnerability, follow [SECURITY.md](../../SECURITY.md) — please do not open a public issue.

## Built-in protections

| Threat | Mitigation |
|---|---|
| Brute-force login | Lockout after 5 failed attempts in 15 minutes, counted per IP **and** per account; random delay on failure; password verification runs even for unknown e-mails (no user enumeration by timing). |
| Account takeover | `password_hash` (PHP default, rehash on login), RFC 6238 TOTP two-factor authentication with replay protection, minimum password length 10, password reset tokens stored hashed and valid for one hour. |
| Session theft | `HttpOnly`, `SameSite=Lax` and (on HTTPS) `Secure` cookies; session ID regenerated at login; session bound to a browser fingerprint; sessions stored in `veri/oturumlar/`. |
| CSRF | Session-bound token checked with `hash_equals` on every panel POST. |
| XSS | `{{ }}` output is escaped by default. Rich text passes a DOM-based **allow-list** sanitizer: `script`, `on*` attributes and `javascript:`-style URLs are removed (control characters and whitespace are stripped before the scheme check); iframes only from YouTube (incl. nocookie), Vimeo, Google Maps, Spotify and Dailymotion over HTTPS. JSON-LD is encoded with `<`, `>` and `&` escaped so `</script>` cannot break out. HTML pasted or previewed in the panel is parsed in an inert `DOMParser` document. |
| Template injection | Any `<?` sequence in a template is neutralised at compile time. |
| SQL injection | PDO prepared statements only; dynamic column/table names come from fixed lists. |
| Malicious uploads | Extension allow-list, `finfo` MIME check, scan for `<?php` in contents, PHP execution disabled in `yuklemeler/`; SVG and HTML uploads are rejected. |
| Sensitive files | `veri/`, `bozkurt/`, `sablonlar/` etc. are blocked by `.htaccess`, `web.config` and the Nginx example; random SQLite file name; config file mode `0600`; installer locked once installed. |
| SSRF | Webhook and AI endpoints must resolve to public IPs (private/reserved ranges refused); webhook connections are pinned to the validated IP (DNS rebinding) and redirects are not followed. The updater only downloads from GitHub and verifies a SHA-256 checksum. |
| Credential leakage over SMTP | If STARTTLS cannot be established the connection is closed; credentials are never sent in plain text. |
| Form spam | Honeypot field, HMAC-signed time token (3 s – 24 h), origin check, per-IP rate limit. |
| API abuse | Keys stored as SHA-256 hashes, read/write scopes, 120 requests/minute, writes logged. |
| Privilege escalation | Roles with explicit permissions; authors cannot publish (including via inline editing); MCP write tools only create drafts. |
| Clickjacking & co. | `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`; Content-Security-Policy in the admin panel; HSTS when the site URL is HTTPS. |
| Privacy (KVKK) | IP anonymisation, EXIF/GPS removal from uploaded photos, analytics only after cookie consent, configurable retention period for form data. |

## Recommended hardening

1. Require 2FA for every administrator.
2. Enable SSL and the HTTPS redirect in `.htaccess`.
3. Restrict `/yonetim/` by IP (hosting panel) or put it behind an access proxy such as Cloudflare Access.
4. Download full backups regularly and store them privately (they contain hashes and secrets).
5. Only enable **trust reverse-proxy headers** when the site really is behind Cloudflare or a proxy.
6. Keep `hata_ayiklama` (debug) off in production.

## Security tests

`testler/kapsamli.php` contains attacker scenarios: `</script>` break-out through titles into JSON-LD,
control-character `javascript:` links, reflected XSS in search, SQL-injection attempts in URLs and API
parameters, PHP disguised as an image, malicious backup files, forged payment callbacks, path traversal,
brute-force lockout (including spoofed proxy headers), password-reset enumeration and privilege escalation
by authors. `testler/birim.php` covers the sanitizer, template compiler, validators and the SSRF helper. See [testing.md](testing.md).

Historical audit reports (Turkish): [GUVENLIK-DENETIMI.md](../GUVENLIK-DENETIMI.md), [SON-TARAMA.md](../SON-TARAMA.md).
