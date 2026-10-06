<?php
declare(strict_types=1);

use Bozkurt\App;
use Bozkurt\Lang;

/** HTML kaçışı */
function e(mixed $v): string
{
    if (is_array($v)) {
        $v = $v['baslik'] ?? '';
    }
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Çeviri */
function t(string $key, mixed ...$args): string
{
    return Lang::get($key, ...$args);
}

function bz_now(): string
{
    return date('Y-m-d H:i:s');
}

function bz_redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

function bz_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = str_starts_with(App::requestOrigin(), 'https://');
    session_name('BZOTURUM');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => App::$basePath,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!is_dir(BZ_DATA . '/oturumlar') && is_writable(BZ_DATA)) {
        @mkdir(BZ_DATA . '/oturumlar', 0700, true);
    }
    if (is_dir(BZ_DATA . '/oturumlar') && is_writable(BZ_DATA . '/oturumlar')) {
        session_save_path(BZ_DATA . '/oturumlar');
    }
    session_start();
}

function bz_csrf_token(): string
{
    bz_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function bz_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . bz_csrf_token() . '">';
}

function bz_csrf_check(): bool
{
    bz_session();
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
}

function bz_flash(?string $msg = null, string $type = 'basari'): ?array
{
    bz_session();
    if ($msg !== null) {
        $_SESSION['_flash'][] = ['mesaj' => $msg, 'tur' => $type];
        return null;
    }
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function bz_ip(): string
{
    // Vekil başlıkları (Cloudflare vb.) yalnızca Ayarlar > Güvenlik'te açıkça güvenildiyse okunur;
    // aksi hâlde saldırgan sahte başlıkla hız sınırını atlatabilirdi.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (App::installed() && App::trustProxy()) {
        $fwd = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
        if ($fwd !== '' && filter_var($fwd, FILTER_VALIDATE_IP)) {
            $ip = $fwd;
        }
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** KVKK: IP anonimleştirme (son okteti sıfırlar) */
function bz_ip_store(): string
{
    $ip = bz_ip();
    if (App::setting('ip_anonim', '1') === '1') {
        if (str_contains($ip, ':')) {
            return preg_replace('/:[0-9a-f]*:[0-9a-f]*$/i', ':0:0', $ip) ?? $ip;
        }
        return preg_replace('/\.\d+$/', '.0', $ip) ?? $ip;
    }
    return $ip;
}

function bz_json(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function bz_log(string $action, string $detail = ''): void
{
    try {
        $u = \Bozkurt\Auth::user();
        App::db()->insert('bz_gunluk', [
            'kullanici_id' => $u['id'] ?? null,
            'islem' => $action,
            'detay' => mb_substr($detail, 0, 500),
            'ip' => bz_ip_store(),
            'tarih' => bz_now(),
        ]);
    } catch (\Throwable) {
    }
}

function bz_security_headers(bool $admin = false): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    if (App::installed() && App::isHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if ($admin) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; connect-src 'self'; frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com https://www.google.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function bz_upload_url(?string $rel): string
{
    if (!$rel) {
        return '';
    }
    if (preg_match('#^https?://#', $rel)) {
        return $rel;
    }
    return App::$basePath . ltrim($rel, '/');
}

/** Paneldeki çevrilebilir metin (yönetici dili İngilizce ise sözlükten çevrilir). */
function __(string $tr): string
{
    return \Bozkurt\Lang::admin($tr);
}

/** Excel/CSV formül enjeksiyonuna karşı hücre temizliği */
function bz_csv_cell(mixed $v): string
{
    $v = (string) $v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

/** SSRF koruması: yalnızca genel internetteki http(s) adreslerine izin verir. */
function bz_public_url(string $url): bool
{
    $p = parse_url($url);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
        return false;
    }
    $ips = filter_var($p['host'], FILTER_VALIDATE_IP) ? [$p['host']] : (gethostbynamel($p['host']) ?: []);
    if (!$ips) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

/** Basit HTTP istemcisi (cURL varsa cURL, yoksa akış). @return array{0:int,1:string} */
function bz_http(string $method, string $url, ?string $body = null, array $headers = [], int $timeout = 20): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout), CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'BOZKURT-CMS/' . BZ_VERSION,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, $res === false ? '' : (string) $res];
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", array_merge($headers, ['User-Agent: BOZKURT-CMS/' . BZ_VERSION])),
        'content' => $body ?? '', 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
            $code = (int) $m[1];
        }
    }
    return [$code, $res === false ? '' : (string) $res];
}
