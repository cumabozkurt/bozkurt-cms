# Katkı Rehberi

Katkılarınızı bekliyoruz! 🐺

## Geliştirme ortamı

```bash
git clone https://github.com/cumabozkurt/bozkurt-cms.git && cd bozkurt-cms
php -S localhost:8000 yonlendirici.php     # http://localhost:8000/yonetim/
```

PHP 8.1+ ve `pdo_sqlite`, `mbstring`, `gd`, `zip`, `dom`, `fileinfo` eklentileri yeterlidir.

## Kurallar

1. `ozellik/kisa-ad` veya `duzeltme/kisa-ad` adında bir dal açın.
2. PSR-12, `declare(strict_types=1);`, **PHP 8.1 uyumlu** kod. Composer/npm bağımlılığı eklemeyin; paylaşımlı hosting uyumluluğu temel ilkemizdir.
3. Kullanıcıya görünen panel metinleri Türkçe yazılır; İngilizce karşılığı `bozkurt/lang/panel-en.php` (JS için `panel-en-js.php`) dosyasına eklenir.
4. Şablonlardaki iç bağlantılarda `{{ site.onek }}` kullanın (çok dilli sitelerde dil önekini korur).
5. Yeni özellikte `README.md`, `docs/SABLON-REHBERI.md` ve `CHANGELOG.md` güncellenir.

## Göndermeden önce

```bash
find . -name "*.php" -not -path "./.git/*" -print0 | xargs -0 -n1 php -l
php testler/duman.php
php testler/kapsamli.php      # MySQL için: BZ_TEST_MYSQL="127.0.0.1;3306;db;kullanici;sifre"
php testler/tarama.php
phpstan analyse -c phpstan.neon.dist
```

CI aynı denetimleri PHP 8.1–8.4 ve MySQL üzerinde çalıştırır.

## Sürüm yayınlama (bakımcılar)

1. `bozkurt/boot.php` içindeki `BZ_VERSION` ve `CHANGELOG.md` güncellenir.
2. `git tag v1.0.1 && git push origin v1.0.1`
3. **Yayın** iş akışı testleri çalıştırır, `bozkurt-cms-1.0.1.zip` ve `.sha256` dosyasını üretip GitHub Release'e ekler. Paneldeki tek tıkla güncelleme bu dosyaları kullanır.

Güvenlik açıkları için issue açmayın; [SECURITY.md](SECURITY.md)'deki yolu izleyin.
