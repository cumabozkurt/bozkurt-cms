<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Web kancaları: içerik kaydı/silme, form gönderimi, ödeme olaylarını Zapier, Make, n8n, Slack vb. adreslere iletir.
 * Gövde HMAC-SHA256 ile imzalanır: X-Bozkurt-Imza: sha256=<hex>
 */
final class Webhook
{
    public static function fire(string $event, array $data): void
    {
        $urls = array_filter(array_map('trim', preg_split('/\R/', (string) App::setting('webhook_adresleri', '')) ?: []));
        if (!$urls) {
            return;
        }
        $body = (string) json_encode(['olay' => $event, 'zaman' => date('c'), 'site' => App::siteUrl(), 'veri' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig = 'sha256=' . hash_hmac('sha256', $body, (string) App::setting('webhook_gizli', App::$config['anahtar'] ?? ''));
        foreach (array_slice($urls, 0, 5) as $url) {
            if (!bz_public_url($url)) {
                error_log("Webhook reddedildi (özel/geçersiz adres): $url");
                continue;
            }
            $headers = ['Content-Type: application/json', 'X-Bozkurt-Olay: ' . $event, 'X-Bozkurt-Imza: ' . $sig];
            $host = (string) parse_url($url, PHP_URL_HOST);
            if (function_exists('curl_init') && !filter_var($host, FILTER_VALIDATE_IP)) {
                // DNS yeniden bağlama saldırısına karşı: doğrulanan IP'ye sabitle
                $ip = gethostbyname($host);
                $port = (int) (parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https') ? 443 : 80));
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false, CURLOPT_RESOLVE => ["$host:$port:$ip"], CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
                curl_exec($ch);
                curl_close($ch);
            } else {
                bz_http('POST', $url, $body, $headers, 4);
            }
        }
    }
}
