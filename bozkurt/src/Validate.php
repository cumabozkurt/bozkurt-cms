<?php
declare(strict_types=1);

namespace Bozkurt;

/** Türkiye'ye özgü doğrulayıcılar (kimlik numaraları saklanmadan önce algoritmik kontrol). */
final class Validate
{
    public const ILLER = ['Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 'Ardahan', 'Artvin',
        'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale',
        'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep',
        'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman',
        'Kars', 'Kastamonu', 'Kayseri', 'Kilis', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya',
        'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun', 'Siirt',
        'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak'];

    /** Sabit tarihli resmî tatiller (ay-gün). Dinî bayramlar Ayarlar > Türkiye'den eklenir. */
    public const SABIT_TATILLER = ['01-01' => 'Yılbaşı', '04-23' => 'Ulusal Egemenlik ve Çocuk Bayramı', '05-01' => 'Emek ve Dayanışma Günü',
        '05-19' => 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı', '07-15' => 'Demokrasi ve Millî Birlik Günü', '08-30' => 'Zafer Bayramı',
        '10-29' => 'Cumhuriyet Bayramı'];

    public static function tckn(string $n): bool
    {
        if (!preg_match('/^[1-9]\d{10}$/', $n)) {
            return false;
        }
        $d = array_map('intval', str_split($n));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        $d10 = (($odd * 7) - $even) % 10;
        if ($d10 < 0) {
            $d10 += 10;
        }
        return $d10 === $d[9] && (array_sum(array_slice($d, 0, 10)) % 10) === $d[10];
    }

    public static function vkn(string $n): bool
    {
        if (!preg_match('/^\d{10}$/', $n)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $t = ((int) $n[$i] + 10 - ($i + 1)) % 10;
            $p = $t === 9 ? 9 : ($t * (2 ** (10 - ($i + 1)))) % 9;
            $sum += $p;
        }
        return ((10 - ($sum % 10)) % 10) === (int) $n[9];
    }

    public static function iban(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban) || (str_starts_with($iban, 'TR') && strlen($iban) !== 26)) {
            return false;
        }
        $r = substr($iban, 4) . substr($iban, 0, 4);
        $num = '';
        foreach (str_split($r) as $c) {
            $num .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }
        $mod = 0;
        foreach (str_split($num, 7) as $chunk) {
            $mod = ((int) ($mod . $chunk)) % 97;
        }
        return $mod === 1;
    }

    /** Verilen tarih resmî tatil mi? Döndürür: tatil adı veya null */
    public static function holiday(string $date): ?string
    {
        $ts = strtotime($date);
        if (!$ts) {
            return null;
        }
        $md = date('m-d', $ts);
        if (isset(self::SABIT_TATILLER[$md])) {
            return self::SABIT_TATILLER[$md];
        }
        foreach (preg_split('/\R/', (string) App::setting('ek_tatiller', '')) ?: [] as $line) {
            [$d, $name] = array_pad(array_map('trim', explode('=', $line, 2)), 2, 'Resmî tatil');
            if ($d === date('Y-m-d', $ts)) {
                return $name;
            }
        }
        return null;
    }
}
