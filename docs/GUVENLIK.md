# Güvenlik Modeli

## Varsayılan korumalar

| Tehdit | Önlem |
|---|---|
| Kaba kuvvet girişi | IP ve hesap başına 15 dakikada 5 hatalı deneme sonrası kilit; hatalı girişte rastgele gecikme |
| Hesap ele geçirme | `password_hash` (PHP varsayılanı, otomatik yeniden hash), RFC 6238 TOTP iki adımlı doğrulama, en az 10 karakter şifre |
| Oturum çalma | `HttpOnly`, `SameSite=Lax`, HTTPS'te `Secure` çerez; girişte oturum kimliği yenileme; tarayıcı parmak izi bağlama; oturum dosyaları `veri/oturumlar/` içinde |
| CSRF | Paneldeki her POST isteğinde oturuma bağlı belirteç (`hash_equals`) |
| XSS | Şablonlarda `{{ }}` varsayılan olarak kaçışlı; zengin metin DOM tabanlı **izin listesi** temizleyiciden geçer (`script`, `on*` öznitelikleri, `javascript:` bağlantıları silinir; iframe yalnızca YouTube (nocookie dahil)/Vimeo/Google Maps/Spotify/Dailymotion; URL şeması denetlenmeden önce kontrol karakterleri ve boşluklar atılır). JSON-LD çıktısında `<`, `>`, `&` kaçışlanır (`</script>` kaçışı yapılamaz). Panelde yapıştırılan/önizlenen HTML, betik çalıştırmayan `DOMParser` belgesinde ayrıştırılır |
| SQL enjeksiyonu | Yalnızca PDO hazır ifadeleri; dinamik sütun/tablo adları sabit listelerden |
| Kötü amaçlı yükleme | Uzantı izin listesi + `finfo` MIME doğrulaması + içerikte `<?php` taraması + `yuklemeler/` altında PHP çalıştırma kapalı; SVG ve HTML yüklenemez |
| Hassas dosyalara erişim | `veri/`, `bozkurt/`, `sablonlar/` web'den kapalı (`.htaccess`, `web.config`, Nginx örneği); SQLite dosya adı rastgele; yapılandırma `0600` |
| Form spam'i | Bal küpü alanı, HMAC imzalı zaman belirteci (3 sn – 24 saat), köken denetimi, IP başına hız sınırı |
| SSRF | Web kancası ve YZ adresleri yalnızca genel IP'lere gider (özel/ayrılmış aralıklar reddedilir); web kancası bağlantısı doğrulanan IP'ye sabitlenir (DNS rebinding'e karşı), yönlendirme izlenmez |
| E-posta kimlik bilgileri | SMTP'de STARTTLS kurulamazsa bağlantı kesilir; şifre asla düz metin gönderilmez |
| Tıklama hırsızlığı vb. | `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` |
| Şablon enjeksiyonu | Şablon metnindeki `<?` dizileri derlemede etkisizleştirilir |
| Gizlilik (KVKK) | IP anonimleştirme, yüklenen fotoğraflardan EXIF/konum silme, analitik yalnızca çerez onayıyla, veri saklama süresi |

## Önerilen ek adımlar

1. Yönetici hesaplarında 2FA'yı zorunlu tutun.
2. SSL'i etkinleştirip `.htaccess`'teki HTTPS yönlendirmesini açın.
3. `/yonetim/` için Cloudflare Access veya hosting panelinden IP kısıtlaması ekleyin.
4. Düzenli olarak tam yedek indirin.

## Açık bildirimi

Bir güvenlik açığı bulursanız lütfen herkese açık issue açmak yerine depo sahibine e-postayla bildirin. 72 saat içinde yanıt, kritik açıklar için 14 gün içinde yama hedeflenir.
