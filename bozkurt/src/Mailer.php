<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Bağımlılıksız SMTP istemcisi (SSL/465, STARTTLS/587). Hostinger, Yandex, Gmail, Turkcell vb. ile çalışır.
 * SMTP ayarlanmamışsa PHP mail() kullanılır.
 */
final class Mailer
{
    public static string $lastError = '';

    public static function send(string $to, string $subject, string $html, ?string $replyTo = null): bool
    {
        $from = App::setting('smtp_gonderen', App::setting('smtp_kullanici', 'noreply@' . (parse_url(App::siteUrl(), PHP_URL_HOST) ?: 'localhost')));
        $fromName = App::setting('site_adi', 'BOZKURT CMS');
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'From: ' . self::encode($fromName) . ' <' . $from . '>',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(App::siteUrl(), PHP_URL_HOST) ?: 'localhost') . '>',
        ];
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $body = chunk_split(base64_encode('<!doctype html><meta charset="utf-8"><div style="font-family:system-ui,sans-serif;font-size:15px">' . $html . '</div>'));
        $host = App::setting('smtp_sunucu');
        if (!$host) {
            return @mail($to, self::encode($subject), $body, implode("\r\n", $headers));
        }
        try {
            return self::smtp($host, (int) App::setting('smtp_port', '465'), App::setting('smtp_guvenlik', 'ssl'),
                App::setting('smtp_kullanici', ''), App::setting('smtp_sifre', ''), $from, $to,
                "To: $to\r\nSubject: " . self::encode($subject) . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body);
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('BOZKURT SMTP: ' . $e->getMessage());
            return false;
        }
    }

    private static function encode(string $s): string
    {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private static function smtp(string $host, int $port, string $sec, string $user, string $pass, string $from, string $to, string $data): bool
    {
        $remote = ($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("Bağlantı kurulamadı: $errstr ($errno)");
        }
        stream_set_timeout($fp, 15);
        $read = function () use ($fp): string {
            $res = '';
            while (($line = fgets($fp, 515)) !== false) {
                $res .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $res;
        };
        $cmd = function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) {
                throw new \RuntimeException('SMTP hatası: ' . trim($r));
            }
            return $r;
        };
        $read();
        $ehlo = 'EHLO ' . (parse_url(App::siteUrl(), PHP_URL_HOST) ?: 'localhost');
        $cmd($ehlo, [250]);
        if ($sec === 'tls') {
            $cmd('STARTTLS', [220]);
            if (stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) !== true) {
                fclose($fp);
                // Şifreleme kurulamadıysa kullanıcı adı/şifre asla düz metin gönderilmez
                throw new \RuntimeException('STARTTLS ile şifreli bağlantı kurulamadı.');
            }
            $cmd($ehlo, [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $cmd(str_replace("\n.", "\n..", $data) . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }
}
