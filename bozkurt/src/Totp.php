<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * RFC 6238 TOTP — Google Authenticator, Microsoft Authenticator, Authy ile uyumlu iki adımlı doğrulama.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $len = 32): string
    {
        $s = '';
        for ($i = 0; $i < $len; $i++) {
            $s .= self::ALPHABET[random_int(0, 31)];
        }
        return $s;
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $key = self::base32Decode($secret);
        $counter = intdiv($time ?? time(), 30);
        $bin = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $num = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Doğruysa kullanılan zaman adımını döndürür (tekrar oynatma koruması için). */
    public static function verifyStep(string $secret, string $code): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return null;
        }
        for ($w = -1; $w <= 1; $w++) {
            $t = time() + $w * 30;
            if (hash_equals(self::code($secret, $t), $code)) {
                return intdiv($t, 30);
            }
        }
        return null;
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }
        for ($w = -1; $w <= 1; $w++) {
            if (hash_equals(self::code($secret, time() + $w * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $account, string $issuer = 'BOZKURT CMS'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) .
            '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }

    private static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
