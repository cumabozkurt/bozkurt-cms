<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Oturum, yetki ve kaba kuvvet koruması.
 * Roller: yonetici (her şey), editor (içerik + medya + formlar), yazar (yalnızca kendi içerikleri).
 */
final class Auth
{
    public const ROLES = ['yonetici' => 'Yönetici', 'editor' => 'Editör', 'yazar' => 'Yazar'];
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MIN = 15;
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            if (!App::installed()) {
                return null;
            }
            if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE['BZOTURUM'])) {
                return null; // ziyaretçiler için oturum açma (önbellek dostu)
            }
            bz_session();
            $id = $_SESSION['bz_uid'] ?? null;
            $now = time();
            $idle = (int) ($_SESSION['bz_son'] ?? 0);
            $start = (int) ($_SESSION['bz_baslangic'] ?? 0);
            if ($id && (($idle && $now - $idle > 7200) || ($start && $now - $start > 43200))) {
                $_SESSION = []; // 2 saat hareketsizlik veya 12 saatlik mutlak süre → yeniden giriş
                $id = null;
            }
            if ($id && ($_SESSION['bz_ua'] ?? '') === self::fingerprint()) {
                $u = App::db()->one('SELECT id, ad, eposta, rol, totp_gizli, son_giris, dil, oturum_surumu FROM bz_kullanicilar WHERE id = ?', [$id]);
                // Şifre değişince diğer oturumlar geçersiz olur
                if ($u && (int) $u['oturum_surumu'] === (int) ($_SESSION['bz_surum'] ?? 0)) {
                    self::$user = $u;
                    $_SESSION['bz_son'] = $now;
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        $map = [
            'yonetici' => ['*'],
            'editor' => ['icerik', 'icerik_tum', 'medya', 'formlar', 'genel', 'yayinla'],
            'yazar' => ['icerik', 'medya'],
        ];
        $perms = $map[$u['rol']] ?? [];
        return in_array('*', $perms, true) || in_array($perm, $perms, true);
    }

    public static function tooManyAttempts(?string $email = null): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);
        $n = (int) App::db()->val('SELECT COUNT(*) FROM bz_giris_denemeleri WHERE ip = ? AND tarih > ?', [bz_ip(), $since]);
        if ($n >= self::MAX_ATTEMPTS) {
            return true;
        }
        // Hesap bazlı kilit: dağıtık (çok IP'li) kaba kuvvet saldırısına karşı
        if ($email !== null) {
            $m = (int) App::db()->val('SELECT COUNT(*) FROM bz_giris_denemeleri WHERE ip = ? AND tarih > ?', [self::accountKey($email), $since]);
            return $m >= self::MAX_ATTEMPTS * 3;
        }
        return false;
    }

    private static function accountKey(string $email): string
    {
        return 'h:' . substr(hash('sha256', Str::lower(trim($email))), 0, 40);
    }

    /** @return array{ok:bool, totp?:bool, hata?:string} */
    public static function attempt(string $email, string $password): array
    {
        if (self::tooManyAttempts($email)) {
            return ['ok' => false, 'hata' => t('giris_kilit', self::WINDOW_MIN)];
        }
        $u = App::db()->one('SELECT * FROM bz_kullanicilar WHERE eposta = ?', [Str::lower(trim($email))]);
        // Kullanıcı yoksa da hash doğrulaması yapılır (zamanlamadan kullanıcı tespiti engellenir)
        $hash = $u['sifre'] ?? password_hash('bz-sahte-' . random_int(0, 9), PASSWORD_DEFAULT);
        if (!password_verify(substr($password, 0, 4096), $hash) || !$u) {
            App::db()->insert('bz_giris_denemeleri', ['ip' => bz_ip(), 'tarih' => bz_now()]);
            App::db()->insert('bz_giris_denemeleri', ['ip' => self::accountKey($email), 'tarih' => bz_now()]);
            usleep(random_int(200000, 500000));
            return ['ok' => false, 'hata' => t('giris_hatali')];
        }
        if (password_needs_rehash($u['sifre'], PASSWORD_DEFAULT)) {
            App::db()->update('bz_kullanicilar', ['sifre' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        bz_session();
        if (!empty($u['totp_gizli'])) {
            $_SESSION['bz_totp_bekleyen'] = $u['id'];
            return ['ok' => false, 'totp' => true];
        }
        self::login((int) $u['id']);
        return ['ok' => true];
    }

    public static function verifyTotp(string $code): bool
    {
        bz_session();
        $id = $_SESSION['bz_totp_bekleyen'] ?? null;
        if (!$id || self::tooManyAttempts()) {
            return false;
        }
        $u = App::db()->one('SELECT id, totp_gizli, totp_son FROM bz_kullanicilar WHERE id = ?', [$id]);
        $step = $u ? Totp::verifyStep($u['totp_gizli'], $code) : null;
        // Aynı kod ikinci kez kullanılamaz (tekrar oynatma koruması)
        if ($u && $step !== null && $step > (int) $u['totp_son']) {
            App::db()->update('bz_kullanicilar', ['totp_son' => $step], 'id = ?', [$u['id']]);
            unset($_SESSION['bz_totp_bekleyen']);
            self::login((int) $u['id']);
            return true;
        }
        App::db()->insert('bz_giris_denemeleri', ['ip' => bz_ip(), 'tarih' => bz_now()]);
        return false;
    }

    public static function login(int $id): void
    {
        bz_session();
        session_regenerate_id(true);
        $_SESSION['bz_uid'] = $id;
        $_SESSION['bz_ua'] = self::fingerprint();
        $_SESSION['bz_baslangic'] = $_SESSION['bz_son'] = time();
        $_SESSION['bz_surum'] = (int) App::db()->val('SELECT oturum_surumu FROM bz_kullanicilar WHERE id = ?', [$id]);
        App::db()->update('bz_kullanicilar', ['son_giris' => bz_now()], 'id = ?', [$id]);
        App::db()->q('DELETE FROM bz_giris_denemeleri WHERE ip = ?', [bz_ip()]);
        self::$loaded = false;
        bz_log('giris');
    }

    public static function logout(): void
    {
        bz_session();
        bz_log('cikis');
        $_SESSION = [];
        session_destroy();
        setcookie('BZOTURUM', '', time() - 3600, App::$basePath);
        self::$user = null;
    }

    private static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . (App::$config['anahtar'] ?? ''));
    }

    /* ------------------------------------------------------------ şifre sıfırlama */

    /** Her durumda aynı yanıt verilir (e-posta adresi var mı yok mu sızdırılmaz). */
    public static function requestReset(string $email): void
    {
        if (self::tooManyAttempts()) {
            return;
        }
        App::db()->insert('bz_giris_denemeleri', ['ip' => bz_ip(), 'tarih' => bz_now()]);
        $u = App::db()->one('SELECT id, ad, eposta FROM bz_kullanicilar WHERE eposta = ?', [Str::lower(trim($email))]);
        if (!$u) {
            return;
        }
        $plain = bin2hex(random_bytes(32));
        App::db()->q('DELETE FROM bz_sifre_sifirlama WHERE kullanici_id = ? OR son < ?', [$u['id'], bz_now()]);
        App::db()->insert('bz_sifre_sifirlama', ['kullanici_id' => $u['id'], 'token_hash' => hash('sha256', $plain), 'son' => date('Y-m-d H:i:s', time() + 3600)]);
        $link = App::siteUrl() . App::adminUrl('sifre_yenile', ['t' => $plain]);
        Mailer::send($u['eposta'], 'Şifre sıfırlama — ' . App::setting('site_adi', 'BOZKURT CMS'),
            '<p>Merhaba ' . e($u['ad']) . ',</p><p>Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayın. Bağlantı 1 saat geçerlidir ve yalnızca bir kez kullanılabilir.</p>' .
            '<p><a href="' . e($link) . '" style="background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none">Şifremi sıfırla</a></p>' .
            '<p style="color:#888">Bu isteği siz yapmadıysanız e-postayı yok sayın. İstek IP: ' . e(bz_ip_store()) . '</p>');
        bz_log('sifre_sifirlama_istegi', $u['eposta']);
    }

    public static function resetUser(string $plain): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $plain)) {
            return null;
        }
        return App::db()->one('SELECT r.*, k.eposta FROM bz_sifre_sifirlama r JOIN bz_kullanicilar k ON k.id = r.kullanici_id WHERE r.token_hash = ? AND r.son > ? AND r.kullanildi = 0',
            [hash('sha256', $plain), bz_now()]);
    }

    public static function completeReset(string $plain, string $password): bool
    {
        $r = self::resetUser($plain);
        if (!$r || strlen($password) < 10) {
            return false;
        }
        App::db()->update('bz_sifre_sifirlama', ['kullanildi' => 1], 'id = ?', [$r['id']]);
        App::db()->q('UPDATE bz_kullanicilar SET sifre = ?, oturum_surumu = oturum_surumu + 1 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $r['kullanici_id']]);
        bz_log('sifre_sifirlandi', $r['eposta']);
        return true;
    }
}
