<?php
declare(strict_types=1);

namespace Bozkurt;

final class Lang
{
    private static ?array $strings = null;
    private static array $fallback = [];
    private static ?array $panel = null;

    public static function code(): string
    {
        $c = App::$config['dil'] ?? 'tr';
        return preg_match('/^[a-z]{2}$/', $c) ? $c : 'tr';
    }

    public static function get(string $key, mixed ...$args): string
    {
        if (self::$strings === null) {
            self::$fallback = require BZ_CORE . '/lang/tr.php';
            $file = BZ_CORE . '/lang/' . self::code() . '.php';
            self::$strings = is_file($file) ? require $file : self::$fallback;
        }
        $s = self::$strings[$key] ?? self::$fallback[$key] ?? $key;
        return $args ? vsprintf($s, $args) : $s;
    }

    /** Yöneticinin panel dili (profil tercihi) */
    public static function adminLang(): string
    {
        $u = Auth::user();
        return ($u['dil'] ?? 'tr') === 'en' ? 'en' : 'tr';
    }

    private static function panelDict(): array
    {
        if (self::$panel === null) {
            $f = BZ_CORE . '/lang/panel-en.php';
            self::$panel = is_file($f) ? (require $f) : [];
        }
        return self::$panel;
    }

    public static function admin(string $tr): string
    {
        return self::adminLang() === 'en' ? (self::panelDict()[$tr] ?? $tr) : $tr;
    }

    /**
     * Panel çıktısını sözlükle çevirir. Yalnızca TAM eşleşen metin düğümleri ve
     * placeholder/title/aria-label/data-onay öznitelikleri çevrilir; kullanıcı içeriği
     * (input değerleri, textarea, editör) dokunulmadan kalır.
     */
    public static function translateAdmin(string $html): string
    {
        if (!App::installed() || self::adminLang() !== 'en' || !str_starts_with(ltrim($html), '<')) {
            return $html;
        }
        $d = self::panelDict();
        if (!$d) {
            return $html;
        }
        // Korunacak bölgeler
        $keep = [];
        // Yalnızca İÇERİK korunur; açılış etiketi (placeholder, title) çevrilebilir kalır
        $html = preg_replace_callback('#(<(textarea|script|style|template)\b[^>]*>)(.*?)(</\2>)|(<div class="editor-alan[^"]*"[^>]*>)(.*?)(</div>)#is', function ($m) use (&$keep) {
            $k = "\x01" . count($keep) . "\x01";
            if (($m[1] ?? '') !== '') {
                $keep[$k] = $m[3];
                return $m[1] . $k . $m[4];
            }
            $keep[$k] = $m[6];
            return $m[5] . $k . $m[7];
        }, $html) ?? $html;
        $html = preg_replace_callback('/>([^<>]+)</u', function ($m) use ($d) {
            $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            if ($t === '' || !isset($d[$t])) {
                return $m[0];
            }
            return '>' . str_replace(trim($m[1]), htmlspecialchars($d[$t], ENT_QUOTES, 'UTF-8'), $m[1]) . '<';
        }, $html) ?? $html;
        $html = preg_replace_callback('/\b(placeholder|title|aria-label|data-onay)="([^"]*)"/u', function ($m) use ($d) {
            $t = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            return isset($d[$t]) ? $m[1] . '="' . htmlspecialchars($d[$t], ENT_QUOTES, 'UTF-8') . '"' : $m[0];
        }, $html) ?? $html;
        // Değişken içeren metinler (selamlama, tarih, sayılar, "Düzenle: …")
        $html = preg_replace_callback('/(>|\b(?:title|data-onay|placeholder)=")([^<>"]+)(?=<|")/u', fn($m) => $m[1] . self::patterns($m[2]), $html) ?? $html;
        $html = preg_replace_callback('#(<title>)([^<]+)(</title>)#u', function ($m) use ($d) {
            $parts = explode(' · ', $m[2]);
            $first = html_entity_decode($parts[0], ENT_QUOTES, 'UTF-8');
            $parts[0] = isset($d[$first]) ? htmlspecialchars($d[$first], ENT_QUOTES, 'UTF-8') : self::patterns($parts[0]);
            return $m[1] . implode(' · ', $parts) . $m[3];
        }, $html) ?? $html;
        $html = preg_replace('/<html lang="tr"/', '<html lang="en"', $html, 1) ?? $html;
        return strtr($html, $keep);
    }

    private const MONTHS = ['Ocak' => 'January', 'Şubat' => 'February', 'Mart' => 'March', 'Nisan' => 'April', 'Mayıs' => 'May', 'Haziran' => 'June',
        'Temmuz' => 'July', 'Ağustos' => 'August', 'Eylül' => 'September', 'Ekim' => 'October', 'Kasım' => 'November', 'Aralık' => 'December',
        'Oca' => 'Jan', 'Şub' => 'Feb', 'Mar' => 'Mar', 'Nis' => 'Apr', 'May' => 'May', 'Haz' => 'Jun', 'Tem' => 'Jul', 'Ağu' => 'Aug', 'Eyl' => 'Sep', 'Eki' => 'Oct', 'Kas' => 'Nov', 'Ara' => 'Dec'];
    private const DAYS = ['Pazartesi' => 'Monday', 'Salı' => 'Tuesday', 'Çarşamba' => 'Wednesday', 'Perşembe' => 'Thursday', 'Cuma' => 'Friday', 'Cumartesi' => 'Saturday', 'Pazar' => 'Sunday'];

    /** Değişken parçalı panel metinlerini İngilizceye çevirir. Yalnızca kalıba tam uyan metinlere dokunur. */
    public static function patterns(string $raw): string
    {
        $t = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        $lead = substr($t, 0, strlen($t) - strlen(ltrim($t)));
        $trail = substr($t, strlen(rtrim($t)));
        $x = trim($t);
        if ($x === '' || !preg_match('/[a-zçğıöşüA-ZÇĞİÖŞÜ]/u', $x)) {
            return $raw;
        }
        $d = self::panelDict();
        $mon = implode('|', array_keys(self::MONTHS));
        $day = implode('|', array_keys(self::DAYS));
        $rules = [
            '/^(İyi geceler|Günaydın|İyi günler|İyi akşamlar), (.+?) 👋$/u' => fn($m) => ($d[$m[1]] ?? $m[1]) . ', ' . $m[2] . ' 👋',
            '/^(.+?) · Bugün neyi güncelliyoruz\?$/u' => fn($m) => self::dates($m[1]) . ' · What are we updating today?',
            '/^(Çoklu içerik|Tekil sayfa) · (\d+) alan · (.+)$/u' => fn($m) => ($m[1] === 'Tekil sayfa' ? 'Single page' : 'Collection') . ' · ' . $m[2] . ' field' . ($m[2] === '1' ? '' : 's') . ' · ' . $m[3],
            '/^(\d+) içerik$/u' => fn($m) => $m[1] . ' item' . ($m[1] === '1' ? '' : 's'),
            '/^(\d+) içerik güncellendi\.$/u' => fn($m) => $m[1] . ' items updated.',
            '/^(\d+) kayıt silindi\.$/u' => fn($m) => $m[1] . ' records deleted.',
            '/^(\d+) yönlendirme içe aktarıldı\.$/u' => fn($m) => $m[1] . ' redirects imported.',
            '/^(\d+) içerik eklendi, (\d+) atlandı\.$/u' => fn($m) => $m[1] . ' items added, ' . $m[2] . ' skipped.',
            '/^(\d+) adres tekrar tekrar 404 veriyor\.$/u' => fn($m) => $m[1] . ' URLs keep returning 404.',
            '/^Düzenle: (.+)$/u' => fn($m) => 'Edit: ' . $m[1],
            '/^Çeviri: (.+)$/u' => fn($m) => 'Translation: ' . $m[1],
            '/^“(.+)” kalıcı olarak silinsin mi\?$/u' => fn($m) => 'Permanently delete “' . $m[1] . '”?',
            '/^“(.+)” silindi\.$/u' => fn($m) => '“' . $m[1] . '” deleted.',
            '/^Sunucu sınırı: (.+)$/u' => fn($m) => 'Server limit: ' . $m[1],
            '/^Yüklendi: (.+)$/u' => fn($m) => 'Uploaded: ' . $m[1],
            '/^Sürüm geri yüklendi \((.+)\)\.$/u' => fn($m) => 'Revision restored (' . self::dates($m[1]) . ').',
            '/^Test e-postası (.+) adresine gönderildi\.$/u' => fn($m) => 'Test e-mail sent to ' . $m[1] . '.',
            '/^Gönderilemedi: (.+)$/u' => fn($m) => 'Could not send: ' . $m[1],
            '/^YZ hatası: (.+)$/u' => fn($m) => 'AI error: ' . $m[1],
            '/^YZ bağlantısı çalışıyor: (.+)$/u' => fn($m) => 'AI connection works: ' . $m[1],
            '/^Tüm alanları (.+) diline çevir$/u' => fn($m) => 'Translate all fields into ' . $m[1],
            '/^✨ Tüm alanları (.+) diline çevir$/u' => fn($m) => '✨ Translate all fields into ' . $m[1],
            '/^az önce$/u' => fn() => 'just now',
            '/^(\d+) (dakika|saat|gün) önce$/u' => fn($m) => $m[1] . ' ' . ['dakika' => 'minute', 'saat' => 'hour', 'gün' => 'day'][$m[2]] . ($m[1] === '1' ? '' : 's') . ' ago',
            '/^(.+) · (az önce|\d+ (?:dakika|saat|gün) önce)$/u' => fn($m) => $m[1] . ' · ' . self::patterns($m[2]),
            "/^\\d{1,2} ($mon) \\d{4}(,? (\\d{2}:\\d{2}|$day))?$/u" => fn($m) => self::dates($x),
            "/^($mon) (\\d{4})$/u" => fn($m) => self::MONTHS[$m[1]] . ' ' . $m[2],
            "/^BOZKURT CMS ([\\d.]+) · Açık kaynak \\(MIT\\) · Türkiye'de üretildi 🇹🇷$/u" => fn($m) => 'BOZKURT CMS ' . $m[1] . ' · Open source (MIT) · Made in Türkiye 🇹🇷',
            '/^Toplam tahsilat: (.+)$/u' => fn($m) => 'Total collected: ' . $m[1],
        ];
        foreach ($rules as $re => $fn) {
            if (preg_match($re, $x, $m)) {
                $out = $fn($m);
                return $out === $x ? $raw : htmlspecialchars($lead . $out . $trail, ENT_QUOTES, 'UTF-8');
            }
        }
        return $raw;
    }

    /** "07 Ekim 2026, Çarşamba" → "07 October 2026, Wednesday" */
    private static function dates(string $s): string
    {
        $s = preg_replace_callback('/\b(' . implode('|', array_keys(self::DAYS)) . ')\b/u', fn($m) => self::DAYS[$m[1]], $s) ?? $s;
        return preg_replace_callback('/(\d{1,2}) (' . implode('|', array_keys(self::MONTHS)) . ')\b/u', fn($m) => $m[1] . ' ' . self::MONTHS[$m[2]], $s) ?? $s;
    }
}
