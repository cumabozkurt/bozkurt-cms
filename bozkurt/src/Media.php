<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Medya kütüphanesi: güvenli yükleme, otomatik küçültme, WebP dönüşümü, isteğe bağlı küçük resimler.
 */
final class Media
{
    public const ALLOWED = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'pdf' => 'application/pdf', 'zip' => 'application/zip',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg', 'txt' => 'text/plain', 'csv' => 'text/csv',
    ];

    /** @return array{ok:bool, hata?:string, id?:int, dosya?:string, url?:string} */
    public static function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'hata' => match ($file['error'] ?? 4) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya, sunucunun izin verdiği boyutu aşıyor (' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
                default => 'Yükleme başarısız oldu.',
            }];
        }
        $maxMb = (int) App::setting('azami_yukleme_mb', '20');
        if ($file['size'] > $maxMb * 1048576) {
            return ['ok' => false, 'hata' => "Dosya en fazla {$maxMb} MB olabilir."];
        }
        $orig = (string) $file['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return ['ok' => false, 'hata' => "“.{$ext}” uzantısına izin verilmiyor."];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $isImage = str_starts_with(self::ALLOWED[$ext], 'image/');
        if ($isImage && !str_starts_with($mime, 'image/')) {
            return ['ok' => false, 'hata' => 'Dosya içeriği resim değil.'];
        }
        if (preg_match('/<\?php|<script/i', (string) file_get_contents($file['tmp_name'], false, null, 0, 4096)) && $ext !== 'txt') {
            return ['ok' => false, 'hata' => 'Dosya güvenlik kontrolünden geçemedi.'];
        }
        $sub = date('Y/m');
        $dir = BZ_UPLOADS . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return ['ok' => false, 'hata' => 'Yükleme klasörü oluşturulamadı. Klasör izinlerini kontrol edin.'];
        }
        $base = Str::slug(pathinfo($orig, PATHINFO_FILENAME), 80) ?: 'dosya';
        $name = $base . '.' . $ext;
        $i = 1;
        while (is_file("$dir/$name")) {
            $name = $base . '-' . (++$i) . '.' . $ext;
        }
        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            return ['ok' => false, 'hata' => 'Dosya taşınamadı.'];
        }
        @chmod("$dir/$name", 0644);
        $w = $h = null;
        if ($isImage && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            self::optimize("$dir/$name", (int) App::setting('azami_resim_genislik', '2400'));
            $info = @getimagesize("$dir/$name");
            [$w, $h] = $info ? [$info[0], $info[1]] : [null, null];
        }
        $rel = "yuklemeler/$sub/$name";
        $id = App::db()->insert('bz_medya', [
            'dosya' => $rel, 'orijinal' => mb_substr($orig, 0, 255), 'mime' => $mime, 'boyut' => filesize("$dir/$name"),
            'genislik' => $w, 'yukseklik' => $h, 'alt' => '', 'tarih' => bz_now(),
        ]);
        return ['ok' => true, 'id' => $id, 'dosya' => $rel, 'url' => bz_upload_url($rel)];
    }

    /** Çok büyük resimleri küçültür, EXIF yönünü düzeltir ve meta veriyi atar (KVKK: konum bilgisi). */
    private static function optimize(string $path, int $maxW): void
    {
        if (!function_exists('imagecreatefromstring')) {
            return;
        }
        $info = @getimagesize($path);
        if (!$info) {
            return;
        }
        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (!$img) {
            return;
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = @exif_read_data($path)['Orientation'] ?? 1;
            $img = match ((int) $o) {
                3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img,
            };
        }
        if (imagesx($img) > $maxW) {
            $img = imagescale($img, $maxW) ?: $img;
        }
        self::save($img, $path, $info[2]);
        imagedestroy($img);
    }

    private static function save(\GdImage $img, string $path, int $type, int $q = 82): void
    {
        match ($type) {
            IMAGETYPE_PNG => (imagesavealpha($img, true) && imagepng($img, $path, 8)),
            IMAGETYPE_WEBP => imagewebp($img, $path, $q),
            default => imagejpeg($img, $path, $q),
        };
    }

    /**
     * {{ oge.resim | resim:"400x300" }} → önbelleğe alınmış küçük resim URL'si (WebP destekleniyorsa WebP).
     * mod: kirp (doldur) | sigdir (oranı koru)
     */
    public static function thumb(string $url, string $size, string $mode = 'kirp'): string
    {
        if ($url === '' || !function_exists('imagecreatefromstring')) {
            return $url;
        }
        $rel = ltrim(substr($url, strlen(App::$basePath) - 1), '/');
        if (!str_starts_with($rel, 'yuklemeler/') || str_contains($rel, '..')) {
            return $url;
        }
        $src = BZ_ROOT . '/' . $rel;
        if (!is_file($src) || !preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
            return $url;
        }
        [$tw, $th] = [min(4000, (int) $m[1]), min(4000, (int) $m[2])];
        if ($tw === 0 && $th === 0) {
            return $url;
        }
        $webp = function_exists('imagewebp');
        $ext = $webp ? 'webp' : strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $thumbRel = 'yuklemeler/_kucuk/' . md5($rel . $size . $mode . filemtime($src)) . '.' . $ext;
        $thumb = BZ_ROOT . '/' . $thumbRel;
        if (!is_file($thumb)) {
            $info = @getimagesize($src);
            $img = $info ? @imagecreatefromstring((string) file_get_contents($src)) : false;
            if (!$img) {
                return $url;
            }
            [$w, $h] = [imagesx($img), imagesy($img)];
            if ($tw === 0) {
                $tw = (int) round($w * $th / $h);
            }
            if ($th === 0) {
                $th = (int) round($h * $tw / $w);
            }
            if ($tw >= $w && $th >= $h) {
                imagedestroy($img);
                return $url;
            }
            if ($mode === 'sigdir') {
                $r = min($tw / $w, $th / $h);
                [$tw, $th] = [(int) ($w * $r), (int) ($h * $r)];
                $sx = $sy = 0;
                $sw = $w;
                $sh = $h;
            } else {
                $r = max($tw / $w, $th / $h);
                $sw = (int) ($tw / $r);
                $sh = (int) ($th / $r);
                $sx = (int) (($w - $sw) / 2);
                $sy = (int) (($h - $sh) / 2);
            }
            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);
            @mkdir(dirname($thumb), 0755, true);
            self::save($dst, $thumb, $webp ? IMAGETYPE_WEBP : ($info[2] ?? IMAGETYPE_JPEG), 80);
            imagedestroy($img);
            imagedestroy($dst);
        }
        return bz_upload_url($thumbRel);
    }

    public static function delete(int $id): void
    {
        $m = App::db()->one('SELECT * FROM bz_medya WHERE id = ?', [$id]);
        if ($m && str_starts_with($m['dosya'], 'yuklemeler/') && !str_contains($m['dosya'], '..')) {
            @unlink(BZ_ROOT . '/' . $m['dosya']);
        }
        App::db()->q('DELETE FROM bz_medya WHERE id = ?', [$id]);
    }

    /** "480,800,1200" → srcset dizesi (her genişlik için oranı koruyan küçük resim) */
    public static function srcset(string $url, string $widths): string
    {
        if ($url === '') {
            return '';
        }
        $out = [];
        foreach (array_filter(array_map('intval', explode(',', $widths))) as $w) {
            $w = max(16, min(4000, $w));
            $out[] = self::thumb($url, $w . 'x0', 'sigdir') . ' ' . $w . 'w';
        }
        return implode(', ', $out);
    }
}
