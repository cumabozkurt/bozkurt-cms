<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Ödeme: PayTR iFrame API ve iyzico Checkout Form.
 * Güvenlik: tutar her zaman sunucuda içerikten okunur; bildirimler imza/doğrulama ile kontrol edilir;
 * her sipariş yalnızca bir kez "odendi" durumuna geçer.
 */
final class Payment
{
    public static function provider(): string
    {
        return (string) App::setting('odeme_saglayici', '');
    }

    public static function enabled(): bool
    {
        return match (self::provider()) {
            'paytr' => App::setting('paytr_magaza_no') && App::setting('paytr_anahtar') && App::setting('paytr_tuz'),
            'iyzico' => App::setting('iyzico_api') && App::setting('iyzico_gizli'),
            default => false,
        };
    }

    /** /odeme/... rotaları */
    public static function route(string $sub): void
    {
        match ($sub) {
            'baslat' => self::start(),
            'paytr-bildirim' => self::paytrCallback(),
            'iyzico-donus' => self::iyzicoCallback(),
            'basarili', 'basarisiz' => self::resultPage($sub === 'basarili'),
            default => Site::notFound(),
        };
    }

    private static function start(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !self::enabled()) {
            Site::notFound();
            return;
        }
        if (!Forms::tokenValid((string) ($_POST['_bz_t'] ?? '')) || (int) App::db()->val("SELECT COUNT(*) FROM bz_siparisler WHERE tarih > ? AND musteri LIKE ?", [date('Y-m-d H:i:s', time() - 600), '%"ip":"' . bz_ip() . '"%']) >= 10) {
            self::page('Ödeme başlatılamadı', '<p>Lütfen birkaç dakika sonra tekrar deneyin.</p>');
            return;
        }
        $row = Content::find((int) ($_POST['icerik'] ?? 0));
        $field = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['alan'] ?? 'fiyat'));
        $veri = $row ? (json_decode((string) $row['veri'], true) ?: []) : [];
        $price = (string) ($veri[$field] ?? '');
        $c = [
            'ad_soyad' => mb_substr(trim((string) ($_POST['ad_soyad'] ?? '')), 0, 100),
            'eposta' => trim((string) ($_POST['eposta'] ?? '')),
            'telefon' => preg_replace('/[^\d+]/', '', (string) ($_POST['telefon'] ?? '')) ?? '',
            'adres' => mb_substr(trim((string) ($_POST['adres'] ?? '')), 0, 300),
        ];
        if (!$row || $row['durum'] !== 'yayinda' || !is_numeric($price) || (float) $price <= 0 || $c['ad_soyad'] === ''
            || !filter_var($c['eposta'], FILTER_VALIDATE_EMAIL) || strlen($c['telefon']) < 10 || empty($_POST['kvkk_onay'])) {
            self::page('Ödeme başlatılamadı', '<p>Lütfen tüm alanları doldurup Aydınlatma Metni\'ni onaylayın.</p><p><a href="javascript:history.back()">← Geri dön</a></p>');
            return;
        }
        $oid = 'BZ' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        App::db()->insert('bz_siparisler', [
            'siparis_no' => $oid, 'icerik_id' => $row['id'], 'urun' => mb_substr($row['baslik'], 0, 255), 'tutar' => number_format((float) $price, 2, '.', ''),
            'para' => 'TRY', 'durum' => 'bekliyor', 'saglayici' => self::provider(), 'musteri' => json_encode($c + ['ip' => bz_ip()], JSON_UNESCAPED_UNICODE),
            'ham' => '{}', 'tarih' => bz_now(), 'guncelleme' => bz_now(),
        ]);
        self::provider() === 'paytr' ? self::paytrStart($oid, $row, (float) $price, $c) : self::iyzicoStart($oid, $row, (float) $price, $c);
    }

    /* ------------------------------------------------------------ PayTR */

    private static function paytrStart(string $oid, array $row, float $price, array $c): void
    {
        $mid = (string) App::setting('paytr_magaza_no');
        $key = (string) App::setting('paytr_anahtar');
        $salt = (string) App::setting('paytr_tuz');
        $amount = (string) (int) round($price * 100); // kuruş
        $basket = base64_encode((string) json_encode([[$row['baslik'], number_format($price, 2, '.', ''), 1]], JSON_UNESCAPED_UNICODE));
        $test = App::setting('odeme_test', '1') === '1' ? '1' : '0';
        $ip = bz_ip();
        $noInst = '0';
        $maxInst = '0';
        $cur = 'TL';
        $hashStr = $mid . $ip . $oid . $c['eposta'] . $amount . $basket . $noInst . $maxInst . $cur . $test;
        $token = base64_encode(hash_hmac('sha256', $hashStr . $salt, $key, true));
        $post = [
            'merchant_id' => $mid, 'user_ip' => $ip, 'merchant_oid' => $oid, 'email' => $c['eposta'], 'payment_amount' => $amount,
            'paytr_token' => $token, 'user_basket' => $basket, 'debug_on' => $test, 'no_installment' => $noInst, 'max_installment' => $maxInst,
            'user_name' => $c['ad_soyad'], 'user_address' => $c['adres'] ?: '-', 'user_phone' => $c['telefon'],
            'merchant_ok_url' => App::siteUrl() . App::url('odeme/basarili'), 'merchant_fail_url' => App::siteUrl() . App::url('odeme/basarisiz'),
            'timeout_limit' => '30', 'currency' => $cur, 'test_mode' => $test, 'lang' => App::$lang === 'tr' ? 'tr' : 'en',
        ];
        [$code, $res] = bz_http('POST', 'https://www.paytr.com/odeme/api/get-token', http_build_query($post), ['Content-Type: application/x-www-form-urlencoded']);
        $j = json_decode($res, true);
        if ($code !== 200 || ($j['status'] ?? '') !== 'success') {
            error_log('PayTR token hatası: ' . $res);
            self::page('Ödeme başlatılamadı', '<p>Ödeme sağlayıcısına ulaşılamadı. Lütfen daha sonra tekrar deneyin.</p>');
            return;
        }
        $t = e($j['token']);
        self::page('Güvenli ödeme', '<script src="https://www.paytr.com/js/iframeResizer.min.js"></script><iframe src="https://www.paytr.com/odeme/guvenli/' . $t . '" id="paytriframe" frameborder="0" scrolling="no" style="width:100%;min-height:600px"></iframe><script>iFrameResize({},"#paytriframe");</script>');
    }

    private static function paytrCallback(): never
    {
        $oid = (string) ($_POST['merchant_oid'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $total = (string) ($_POST['total_amount'] ?? '');
        $hash = base64_encode(hash_hmac('sha256', $oid . App::setting('paytr_tuz') . $status . $total, (string) App::setting('paytr_anahtar'), true));
        if (!hash_equals($hash, (string) ($_POST['hash'] ?? ''))) {
            http_response_code(400);
            exit('PAYTR notification failed: bad hash');
        }
        $order = App::db()->one('SELECT * FROM bz_siparisler WHERE siparis_no = ?', [$oid]);
        if ($order && $order['durum'] === 'bekliyor') {
            $ok = $status === 'success';
            // Tutar doğrulaması (taksit farkı olmadıkça total_amount >= sipariş tutarı)
            if ($ok && (int) $total < (int) round((float) $order['tutar'] * 100)) {
                $ok = false;
            }
            self::finish($order, $ok, ['status' => $status, 'total_amount' => $total, 'failed_reason' => $_POST['failed_reason_msg'] ?? '']);
        }
        echo 'OK';
        exit;
    }

    /* ------------------------------------------------------------ iyzico */

    private static function iyzicoBase(): string
    {
        return App::setting('odeme_test', '1') === '1' ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
    }

    /** IYZWSv2 kimlik doğrulama başlığı */
    private static function iyzicoRequest(string $path, array $body): array
    {
        $json = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rnd = (string) (int) (microtime(true) * 1000) . bin2hex(random_bytes(4));
        $sig = hash_hmac('sha256', $rnd . $path . $json, (string) App::setting('iyzico_gizli'));
        $auth = base64_encode('apiKey:' . App::setting('iyzico_api') . '&randomKey:' . $rnd . '&signature:' . $sig);
        [$code, $res] = bz_http('POST', self::iyzicoBase() . $path, $json, [
            'Content-Type: application/json', 'Accept: application/json', 'Authorization: IYZWSv2 ' . $auth, 'x-iyzi-rnd: ' . $rnd,
        ]);
        return json_decode($res, true) ?: ['status' => 'failure', 'errorMessage' => 'HTTP ' . $code];
    }

    private static function iyzicoStart(string $oid, array $row, float $price, array $c): void
    {
        $p = number_format($price, 2, '.', '');
        [$first, $last] = array_pad(explode(' ', $c['ad_soyad'], 2), 2, '-');
        $addr = ['contactName' => $c['ad_soyad'], 'city' => 'Istanbul', 'country' => 'Turkey', 'address' => $c['adres'] ?: '-'];
        $res = self::iyzicoRequest('/payment/iyzipos/checkoutform/initialize/auth/ecom', [
            'locale' => App::$lang === 'tr' ? 'tr' : 'en', 'conversationId' => $oid, 'price' => $p, 'paidPrice' => $p, 'currency' => 'TRY',
            'basketId' => $oid, 'paymentGroup' => 'PRODUCT', 'callbackUrl' => App::siteUrl() . App::url('odeme/iyzico-donus') . '?siparis=' . $oid,
            'buyer' => ['id' => md5($c['eposta']), 'name' => $first, 'surname' => $last, 'gsmNumber' => $c['telefon'], 'email' => $c['eposta'],
                'identityNumber' => '11111111111', 'registrationAddress' => $addr['address'], 'ip' => bz_ip(), 'city' => 'Istanbul', 'country' => 'Turkey'],
            'shippingAddress' => $addr, 'billingAddress' => $addr,
            'basketItems' => [['id' => (string) $row['id'], 'name' => mb_substr($row['baslik'], 0, 100), 'category1' => $row['sablon'], 'itemType' => 'VIRTUAL', 'price' => $p]],
        ]);
        if (($res['status'] ?? '') !== 'success') {
            error_log('iyzico başlatma hatası: ' . json_encode($res));
            self::page('Ödeme başlatılamadı', '<p>Ödeme sağlayıcısına ulaşılamadı. Lütfen daha sonra tekrar deneyin.</p>');
            return;
        }
        App::db()->update('bz_siparisler', ['ham' => json_encode(['token' => $res['token'] ?? ''])], 'siparis_no = ?', [$oid]);
        // iyzico'nun resmi form betiği (checkoutFormContent) — kaynak iyzico'dur
        self::page('Güvenli ödeme', '<div id="iyzipay-checkout-form" class="responsive"></div>' . ($res['checkoutFormContent'] ?? ''));
    }

    private static function iyzicoCallback(): never
    {
        $token = (string) ($_POST['token'] ?? '');
        $oid = (string) ($_GET['siparis'] ?? '');
        $order = App::db()->one('SELECT * FROM bz_siparisler WHERE siparis_no = ?', [$oid]);
        $ok = false;
        if ($order && $token !== '' && $order['durum'] === 'bekliyor') {
            $res = self::iyzicoRequest('/payment/iyzipos/checkoutform/auth/ecom/detail', ['locale' => 'tr', 'conversationId' => $oid, 'token' => $token]);
            $ok = ($res['status'] ?? '') === 'success' && ($res['paymentStatus'] ?? '') === 'SUCCESS'
                && ($res['basketId'] ?? '') === $oid && abs((float) ($res['paidPrice'] ?? 0) - (float) $order['tutar']) < 0.01;
            self::finish($order, $ok, array_intersect_key($res, array_flip(['status', 'paymentStatus', 'paymentId', 'paidPrice', 'errorMessage'])));
        }
        bz_redirect(App::url($ok ? 'odeme/basarili' : 'odeme/basarisiz'));
    }

    /* ------------------------------------------------------------ ortak */

    private static function finish(array $order, bool $ok, array $raw): void
    {
        // Yarış koşuluna karşı koşullu güncelleme: yalnızca "bekliyor" durumundaki sipariş değişir
        $n = App::db()->update('bz_siparisler', ['durum' => $ok ? 'odendi' : 'basarisiz', 'ham' => json_encode($raw, JSON_UNESCAPED_UNICODE), 'guncelleme' => bz_now()],
            "siparis_no = ? AND durum = 'bekliyor'", [$order['siparis_no']]);
        if ($n && $ok) {
            $to = App::setting('bildirim_eposta');
            if ($to) {
                Mailer::send($to, 'Yeni ödeme: ' . $order['siparis_no'], '<p><strong>' . e($order['urun']) . '</strong> için ' . e(Str::tl($order['tutar'])) . ' ödeme alındı.</p>');
            }
            Sms::notify('Yeni odeme: ' . $order['siparis_no'] . ' ' . $order['tutar'] . ' TL');
            Webhook::fire('siparis.odendi', ['siparis_no' => $order['siparis_no'], 'tutar' => $order['tutar'], 'urun' => $order['urun']]);
        }
    }

    private static function resultPage(bool $ok): void
    {
        self::page($ok ? 'Ödemeniz alındı' : 'Ödeme tamamlanamadı', $ok
            ? '<p>Teşekkür ederiz! Siparişiniz alındı, onay e-postası kısa süre içinde gönderilecek.</p><p><a href="' . e(App::url('')) . '">Ana sayfaya dön</a></p>'
            : '<p>Ödemeniz tamamlanamadı. Kartınızdan çekim yapılmadı. Tekrar deneyebilir veya bizimle iletişime geçebilirsiniz.</p><p><a href="' . e(App::url('')) . '">Ana sayfaya dön</a></p>');
    }

    private static function page(string $title, string $body): void
    {
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="' . e(App::$lang) . '"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>' . e($title) . '</title>' .
            '<link rel="stylesheet" href="' . e(App::$basePath) . 'tema/stil.css"><body><main class="kap dar bolum"><h1>' . e($title) . '</h1>' . $body . '</main></body></html>';
    }
}
