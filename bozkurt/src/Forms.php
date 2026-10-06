<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Ziyaretçi formları: oturumsuz (önbellek dostu) spam koruması, KVKK onayı, kayıt ve e-posta bildirimi.
 * Koruma katmanları: bal küpü alanı, imzalı zaman belirteci (en az 3 sn), köken kontrolü, IP başına hız sınırı.
 */
final class Forms
{
    public static function token(): string
    {
        $t = (string) time();
        return $t . '.' . substr(hash_hmac('sha256', $t, App::$config['anahtar'] ?? 'bz'), 0, 20);
    }

    public static function tokenValid(string $tok): bool
    {
        [$t, $sig] = array_pad(explode('.', $tok, 2), 2, '');
        if (!ctype_digit($t) || !hash_equals(substr(hash_hmac('sha256', $t, App::$config['anahtar'] ?? 'bz'), 0, 20), $sig)) {
            return false;
        }
        $age = time() - (int) $t;
        return $age >= 3 && $age <= 86400;
    }

    /** @return array{gonderildi:bool, basarili:bool, hatalar:array, eski:array} */
    public static function handle(string $name, array $a): array
    {
        $state = ['gonderildi' => false, 'basarili' => false, 'hatalar' => [], 'eski' => []];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ($_POST['_bz_form'] ?? '') !== $name) {
            return $state;
        }
        $state['gonderildi'] = true;
        $data = [];
        foreach ($_POST as $k => $v) {
            if (str_starts_with((string) $k, '_bz') || in_array($k, ['web_sitesi_adresi', 'kvkk_onay'], true) || count($data) >= 50) {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_]/i', '', (string) $k);
            $val = is_array($v) ? implode(', ', array_map('strval', array_filter($v, 'is_scalar'))) : (string) $v;
            $data[$key] = mb_substr(trim($val), 0, 5000);
        }
        $state['eski'] = $data;
        $utm = json_decode((string) ($_POST['_bz_utm'] ?? ''), true);
        if (is_array($utm)) {
            foreach (array_slice($utm, 0, 10, true) as $uk => $uv) {
                if (preg_match('/^[a-z_]{2,20}$/', (string) $uk) && is_scalar($uv)) {
                    $data['_' . $uk] = mb_substr((string) $uv, 0, 200);
                }
            }
        }
        if (!empty($_POST['iys_onay'])) {
            $data['_iys_onay'] = 'Evet (' . bz_now() . ')' . (isset($_POST['iys_kanal']) ? ' · ' . implode(', ', array_map('strval', array_filter((array) $_POST['iys_kanal'], 'is_scalar'))) : '');
        }

        // Spam: sessizce "başarılı" göster ama kaydetme
        if (!empty($_POST['web_sitesi_adresi']) || !self::tokenValid((string) ($_POST['_bz_t'] ?? ''))) {
            $state['basarili'] = true;
            return $state;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        $oh = (string) parse_url($origin, PHP_URL_HOST);
        if ($origin !== '' && $oh !== parse_url(App::siteUrl(), PHP_URL_HOST) && $oh !== preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))) {
            $state['hatalar'][] = 'Form doğrulanamadı. Lütfen sayfayı yenileyip tekrar deneyin.';
            return $state;
        }
        $recent = (int) App::db()->val('SELECT COUNT(*) FROM bz_formlar WHERE ip = ? AND tarih > ?',
            [bz_ip_store(), date('Y-m-d H:i:s', time() - 600)]);
        if ($recent >= 5) {
            $state['hatalar'][] = 'Çok fazla gönderim yaptınız. Lütfen birkaç dakika sonra tekrar deneyin.';
            return $state;
        }
        foreach (array_filter(array_map('trim', explode(',', $a['zorunlu'] ?? ''))) as $req) {
            if (($data[$req] ?? '') === '') {
                $state['hatalar'][] = Str::title(str_replace('_', ' ', $req)) . ' alanı zorunludur.';
            }
        }
        foreach ($data as $k => $v) {
            if ($v !== '' && (str_contains($k, 'eposta') || $k === 'email') && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $state['hatalar'][] = 'Geçerli bir e-posta adresi girin.';
            }
        }
        $kvkk = strtolower($a['kvkk'] ?? 'evet') !== 'hayir';
        if ($kvkk && empty($_POST['kvkk_onay'])) {
            $state['hatalar'][] = 'Devam etmek için Aydınlatma Metni\'ni onaylamanız gerekir.';
        }
        if ($state['hatalar']) {
            return $state;
        }
        App::db()->insert('bz_formlar', [
            'form' => $name,
            'veri' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => bz_ip_store(),
            'kvkk_onay' => $kvkk ? 1 : 0,
            'tarih' => bz_now(),
        ]);
        if (strtolower($a['bildirim'] ?? 'evet') !== 'hayir') {
            $to = $a['alici'] ?? App::setting('bildirim_eposta', '');
            if ($to) {
                $body = "<h2>Yeni form gönderimi: " . e($name) . "</h2><table cellpadding=6>";
                foreach ($data as $k => $v) {
                    if (str_starts_with($k, '_')) {
                        continue;
                    }
                    $body .= '<tr><th align="left">' . e(Str::title(str_replace('_', ' ', $k))) . '</th><td>' . nl2br(e($v)) . '</td></tr>';
                }
                $body .= '</table><p style="color:#888">' . e(App::setting('site_adi', 'BOZKURT CMS')) . ' · ' . bz_now() . '</p>';
                $reply = filter_var($data['eposta'] ?? $data['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
                Mailer::send($to, '[' . App::setting('site_adi', 'Site') . '] ' . ($a['konu'] ?? 'Yeni form mesajı'), $body, $reply);
            }
        }
        Sms::notify('Yeni form: ' . $name . ' - ' . mb_substr((string) ($data['ad_soyad'] ?? $data['eposta'] ?? ''), 0, 40));
        Webhook::fire('form.gonderildi', ['form' => $name, 'veri' => $data]);
        $state['basarili'] = true;
        $state['eski'] = [];
        return $state;
    }

    /** KVKK saklama süresini aşan gönderimleri siler (gün; 0 = sınırsız). */
    public static function purgeOld(): void
    {
        $days = (int) App::setting('form_saklama_gun', '0');
        if ($days > 0) {
            App::db()->q('DELETE FROM bz_formlar WHERE tarih < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
        }
    }
}
