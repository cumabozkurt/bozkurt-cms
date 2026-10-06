<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Yapay zekâ yardımcısı. OpenAI uyumlu her uç noktayla çalışır:
 * OpenAI, Azure OpenAI, OpenRouter, Groq, Mistral, DeepSeek, Google Gemini (OpenAI uyumlu), yerel Ollama / LM Studio.
 * Anahtar yalnızca sunucuda saklanır; tarayıcıya gönderilmez.
 */
final class Ai
{
    public static string $lastError = '';

    public static function enabled(): bool
    {
        return (string) App::setting('yz_anahtar', '') !== '' || str_contains((string) App::setting('yz_url', ''), 'localhost');
    }

    /** @param array<int, array{role:string, content:mixed}> $messages */
    public static function chat(array $messages, float $temperature = 0.4, bool $json = false): ?string
    {
        $base = rtrim((string) App::setting('yz_url', 'https://api.openai.com/v1'), '/');
        $local = (bool) preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?/#', $base . '/');
        if (!$local && (!str_starts_with($base, 'https://') || !bz_public_url($base))) {
            self::$lastError = 'YZ adresi genel bir https:// adresi olmalı (yerel model için http://localhost).';
            return null;
        }
        $body = ['model' => App::setting('yz_model', 'gpt-4o-mini'), 'messages' => $messages, 'temperature' => $temperature];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $headers = ['Content-Type: application/json'];
        if ($key = (string) App::setting('yz_anahtar', '')) {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        [$code, $res] = bz_http('POST', $base . '/chat/completions', (string) json_encode($body, JSON_UNESCAPED_UNICODE), $headers, 60);
        $j = json_decode($res, true);
        $text = $j['choices'][0]['message']['content'] ?? null;
        if ($code !== 200 || !is_string($text)) {
            self::$lastError = $j['error']['message'] ?? ('HTTP ' . $code);
            return null;
        }
        return trim($text);
    }

    private static function system(): string
    {
        $brand = App::setting('site_adi', '');
        $tone = App::setting('yz_ton', 'samimi ama profesyonel');
        return "Sen \"$brand\" web sitesinin içerik editörüsün. Üslup: $tone. Türkçe yazarken Türk Dil Kurumu yazım kurallarına uy. "
            . 'Asla uydurma bilgi, istatistik veya alıntı ekleme. Yalnızca istenen çıktıyı ver; açıklama, giriş cümlesi veya markdown kod bloğu ekleme.';
    }

    /**
     * Görevler: seo, ozet, cevir, duzelt, alt, baslik, serbest
     * @return array{ok:bool, sonuc?:mixed, hata?:string}
     */
    public static function task(string $task, string $text, array $opt = []): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'hata' => 'YZ ayarlanmamış. Ayarlar › Yapay Zekâ bölümünden anahtar ekleyin.'];
        }
        $text = mb_substr($text, 0, 24000);
        $lang = App::langName((string) ($opt['dil'] ?? App::defaultLang()));
        $sys = ['role' => 'system', 'content' => self::system()];
        $r = match ($task) {
            'seo' => self::chat([$sys, ['role' => 'user', 'content' => "Aşağıdaki içerik için $lang dilinde arama motoru başlığı (en fazla 60 karakter) ve meta açıklama (140-158 karakter, eyleme çağıran) üret. JSON döndür: {\"baslik\":\"...\",\"aciklama\":\"...\"}\n\nİÇERİK:\n$text"]], 0.5, true),
            'ozet' => self::chat([$sys, ['role' => 'user', 'content' => "Aşağıdaki metni $lang dilinde 2-3 cümlelik akıcı bir özet olarak yaz:\n\n$text"]]),
            'cevir' => self::chat([$sys, ['role' => 'user', 'content' => "Aşağıdaki içeriği $lang diline çevir. HTML etiketlerini, öznitelikleri ve bağlantı adreslerini aynen koru; marka ve özel adları çevirme; yerelleştirilmiş, doğal bir dil kullan. Yalnızca çeviriyi döndür:\n\n$text"]], 0.2),
            'duzelt' => self::chat([$sys, ['role' => 'user', 'content' => "Aşağıdaki metnin yazım, noktalama ve dilbilgisi hatalarını düzelt. Anlamı ve HTML yapısını değiştirme. Yalnızca düzeltilmiş metni döndür:\n\n$text"]], 0.1),
            'baslik' => self::chat([$sys, ['role' => 'user', 'content' => "Aşağıdaki içerik için $lang dilinde 5 farklı, tıklanası ama abartısız başlık öner. JSON döndür: {\"basliklar\":[\"...\"]}\n\n$text"]], 0.8, true),
            'alt' => self::altText($text, $lang),
            'serbest' => self::chat([$sys, ['role' => 'user', 'content' => mb_substr((string) ($opt['komut'] ?? ''), 0, 2000) . "\n\nMETİN:\n$text"]], 0.6),
            default => null,
        };
        if ($r === null) {
            return ['ok' => false, 'hata' => self::$lastError ?: 'Bilinmeyen görev.'];
        }
        if (in_array($task, ['seo', 'baslik'], true)) {
            $j = json_decode(preg_replace('/^```(?:json)?|```$/m', '', $r) ?? $r, true);
            return is_array($j) ? ['ok' => true, 'sonuc' => $j] : ['ok' => false, 'hata' => 'YZ yanıtı çözümlenemedi.'];
        }
        if ($task === 'cevir' || $task === 'duzelt') {
            $r = preg_replace('/^```(?:html)?\s*|\s*```$/', '', $r) ?? $r;
        }
        return ['ok' => true, 'sonuc' => $r];
    }

    /** Görselden erişilebilirlik için alt metin (görüntü destekli modeller) */
    private static function altText(string $rel, string $lang): ?string
    {
        if (!str_starts_with($rel, 'yuklemeler/') || str_contains($rel, '..') || !is_file(BZ_ROOT . '/' . $rel)) {
            self::$lastError = 'Görsel bulunamadı.';
            return null;
        }
        $thumb = Media::thumb(bz_upload_url($rel), '768x0', 'sigdir');
        $path = BZ_ROOT . '/' . ltrim(substr($thumb, strlen(App::$basePath) - 1), '/');
        $file = is_file($path) ? $path : BZ_ROOT . '/' . $rel;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'image/jpeg';
        $data = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
        return self::chat([
            ['role' => 'system', 'content' => self::system()],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => "Bu görsel için $lang dilinde, ekran okuyucular için en fazla 125 karakterlik, \"görseli\" kelimesiyle başlamayan açıklayıcı bir alt metin yaz. Yalnızca metni döndür."],
                ['type' => 'image_url', 'image_url' => ['url' => $data]],
            ]],
        ], 0.3);
    }
}
