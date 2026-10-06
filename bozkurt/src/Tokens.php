<?php
declare(strict_types=1);

namespace Bozkurt;

/** API / MCP erişim anahtarları. Veritabanında yalnızca SHA-256 özeti saklanır. Kapsam: oku | yaz */
final class Tokens
{
    public static function create(string $name, string $scope): string
    {
        $plain = 'bz_' . bin2hex(random_bytes(24));
        App::db()->insert('bz_tokenlar', ['ad' => mb_substr($name, 0, 120) ?: 'Anahtar', 'token_hash' => hash('sha256', $plain),
            'kapsam' => $scope === 'yaz' ? 'yaz' : 'oku', 'olusturma' => bz_now()]);
        bz_log('token_olustur', $name);
        return $plain;
    }

    private static function bearer(): string
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        if ($h === '' && function_exists('getallheaders')) {
            $all = array_change_key_case((array) getallheaders(), CASE_LOWER);
            $h = (string) ($all['authorization'] ?? '');
        }
        return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : '';
    }

    /** İstekteki anahtarı doğrular; geçerliyse token satırını döndürür. */
    public static function fromRequest(): ?array
    {
        $plain = self::bearer() ?: (string) ($_GET['anahtar'] ?? '');
        if (!str_starts_with($plain, 'bz_') || strlen($plain) > 100) {
            return null;
        }
        $row = App::db()->one('SELECT * FROM bz_tokenlar WHERE token_hash = ?', [hash('sha256', $plain)]);
        if ($row && (!$row['son_kullanim'] || strtotime($row['son_kullanim']) < time() - 300)) {
            App::db()->update('bz_tokenlar', ['son_kullanim' => bz_now()], 'id = ?', [$row['id']]);
        }
        return $row;
    }
}
