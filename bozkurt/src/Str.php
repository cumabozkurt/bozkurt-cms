<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Türkçe farkında metin araçları: slug, büyük/küçük harf (İ/ı), tarih, para.
 */
final class Str
{
    private const TR_MAP = [
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i',
        'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        'â' => 'a', 'Â' => 'a', 'î' => 'i', 'Î' => 'i', 'û' => 'u', 'Û' => 'u',
    ];

    public const AYLAR = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz',
        'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    public const AYLAR_KISA = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    public const GUNLER = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    /** "Çağrı Şöleni İstanbul'da!" → "cagri-soleni-istanbulda" */
    public static function slug(string $text, int $max = 120): string
    {
        $text = strtr($text, self::TR_MAP);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($t !== false) {
                $text = $t;
            }
        }
        $text = strtolower($text);
        $text = preg_replace("/['`^\"~]/", '', $text) ?? '';
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim(substr($text, 0, $max), '-');
        return $text;
    }

    public static function lower(string $s): string
    {
        return mb_strtolower(strtr($s, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
    }

    public static function upper(string $s): string
    {
        return mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    }

    public static function title(string $s): string
    {
        $words = preg_split('/(\s+)/u', self::lower($s), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($words as &$w) {
            if (trim($w) !== '') {
                $w = self::upper(mb_substr($w, 0, 1)) . mb_substr($w, 1);
            }
        }
        return implode('', $words);
    }

    public static function excerpt(string $html, int $len = 160): string
    {
        $html = preg_replace('#<(br|/p|/h\d|/li|/div|/blockquote|/pre|/td)\b[^>]*>#i', '$0 ', $html) ?? $html;
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) ?? '');
        if (mb_strlen($text) <= $len) {
            return $text;
        }
        $cut = mb_substr($text, 0, $len);
        $sp = mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $sp ?: $len), ' ,.;:') . '…';
    }

    /**
     * PHP date() biçimini Türkçe ay/gün adlarıyla uygular.
     * F = Ekim, M = Eki, l = Çarşamba, D = Çar
     */
    public static function date(?string $value, string $format = 'd F Y'): string
    {
        if (!$value) {
            return '';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime($value);
        if (!$ts) {
            return '';
        }
        if ($format === 'once') {
            return self::ago($ts);
        }
        $out = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $c = $format[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $out .= $format[++$i];
                continue;
            }
            $out .= match ($c) {
                'F' => self::AYLAR[(int) date('n', $ts)],
                'M' => self::AYLAR_KISA[(int) date('n', $ts)],
                'l' => self::GUNLER[(int) date('w', $ts)],
                'D' => mb_substr(self::GUNLER[(int) date('w', $ts)], 0, 3),
                default => ctype_alpha($c) ? date($c, $ts) : $c,
            };
        }
        return $out;
    }

    public static function ago(int $ts): string
    {
        $d = time() - $ts;
        return match (true) {
            $d < 60 => 'az önce',
            $d < 3600 => floor($d / 60) . ' dakika önce',
            $d < 86400 => floor($d / 3600) . ' saat önce',
            $d < 86400 * 30 => floor($d / 86400) . ' gün önce',
            $d < 86400 * 365 => floor($d / (86400 * 30)) . ' ay önce',
            default => floor($d / (86400 * 365)) . ' yıl önce',
        };
    }

    /** 1234.5 → "1.234,50 ₺" */
    public static function tl(mixed $n, bool $symbol = true): string
    {
        $s = number_format((float) str_replace(',', '.', (string) $n), 2, ',', '.');
        return $symbol ? $s . ' ₺' : $s;
    }

    public static function number(mixed $n, int $dec = 0): string
    {
        return number_format((float) $n, $dec, ',', '.');
    }

    /** 05xx xxx xx xx biçimi ve doğrulama */
    public static function phone(string $p): string
    {
        $d = preg_replace('/\D/', '', $p) ?? '';
        if (str_starts_with($d, '90')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        if (strlen($d) !== 10) {
            return $p;
        }
        return sprintf('0%s %s %s %s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 2), substr($d, 8, 2));
    }

    public static function readingTime(string $html): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags($html))) ?: []);
        return max(1, (int) ceil($words / 200));
    }

    public static function bytes(int $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $v = (float) $b;
        while ($v >= 1024 && $i < 3) {
            $v /= 1024;
            $i++;
        }
        return self::number($v, $i ? 1 : 0) . ' ' . $u[$i];
    }
}
