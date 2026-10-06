<?php
declare(strict_types=1);

namespace Bozkurt;

/** Netgsm üzerinden SMS bildirimi (form gönderimi, ödeme). Ayarlar > Türkiye > SMS */
final class Sms
{
    public static function send(string $to, string $message): bool
    {
        $user = (string) App::setting('netgsm_kullanici', '');
        $pass = (string) App::setting('netgsm_sifre', '');
        $header = (string) App::setting('netgsm_baslik', '');
        if ($user === '' || $pass === '' || $header === '') {
            return false;
        }
        $to = preg_replace('/\D/', '', $to) ?? '';
        $to = substr($to, -10);
        if (strlen($to) !== 10) {
            return false;
        }
        $q = http_build_query(['usercode' => $user, 'password' => $pass, 'gsmno' => '90' . $to, 'message' => mb_substr($message, 0, 900),
            'msgheader' => $header, 'dil' => 'TR']);
        [$code, $res] = bz_http('GET', 'https://api.netgsm.com.tr/sms/send/get?' . $q, null, [], 10);
        $ok = $code === 200 && preg_match('/^(00|01|02)\b/', trim($res));
        if (!$ok) {
            error_log('Netgsm SMS hatası: ' . $res);
        }
        return (bool) $ok;
    }

    /** Yöneticinin bildirim numarasına kısa mesaj */
    public static function notify(string $message): void
    {
        $to = (string) App::setting('sms_bildirim_no', '');
        if ($to !== '') {
            self::send($to, $message);
        }
    }
}
