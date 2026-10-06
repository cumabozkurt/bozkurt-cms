<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Veritabanından bağımsız JSON yedek (SQLite ⇄ MySQL taşımayı da sağlar) + isteğe bağlı yüklemeler ZIP'i.
 */
final class Backup
{
    private const TABLES = ['bz_icerik', 'bz_surumler', 'bz_genel', 'bz_ayarlar', 'bz_medya', 'bz_formlar', 'bz_kullanicilar', 'bz_yonlendirmeler', 'bz_siparisler'];

    public static function export(): array
    {
        $out = ['bozkurt' => BZ_VERSION, 'tarih' => bz_now(), 'tablolar' => []];
        foreach (self::TABLES as $t) {
            $out['tablolar'][$t] = App::db()->all("SELECT * FROM $t");
        }
        return $out;
    }

    public static function download(bool $withFiles): never
    {
        @set_time_limit(300);
        $json = json_encode(self::export(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $stamp = date('Y-m-d-His');
        bz_log('yedek_indir', $withFiles ? 'dosyalarla' : 'yalnızca veri');
        if (!$withFiles || !class_exists('ZipArchive')) {
            header('Content-Type: application/json; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"bozkurt-yedek-$stamp.json\"");
            echo $json;
            exit;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bz');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('veri.json', (string) $json);
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BZ_UPLOADS, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = 'yuklemeler/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(BZ_UPLOADS))), '/');
            if (!str_contains($rel, '/_kucuk/')) {
                $zip->addFile($f->getPathname(), $rel);
            }
        }
        foreach (glob(BZ_TEMPLATES . '/{,*/}*.*', GLOB_BRACE) ?: [] as $f) {
            $zip->addFile($f, 'sablonlar/' . ltrim(substr($f, strlen(BZ_TEMPLATES)), '/'));
        }
        $zip->close();
        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename=\"bozkurt-yedek-$stamp.zip\"");
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** @return true|string */
    public static function restore(array $file): bool|string
    {
        if (($file['error'] ?? 4) !== UPLOAD_ERR_OK) {
            return 'Yedek dosyası yüklenemedi.';
        }
        if (filesize($file['tmp_name']) > 512 * 1048576) {
            return 'Yedek dosyası çok büyük.';
        }
        $raw = (string) file_get_contents($file['tmp_name']);
        if (str_starts_with($raw, 'PK') && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                return 'ZIP açılamadı.';
            }
            $raw = (string) $zip->getFromName('veri.json');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if (str_starts_with($n, 'yuklemeler/') && !str_contains($n, '..') && !str_contains($n, '\\') && !str_ends_with($n, '/') && !str_contains($n, '/.')
                    && isset(Media::ALLOWED[strtolower(pathinfo($n, PATHINFO_EXTENSION))])) {
                    @mkdir(dirname(BZ_ROOT . '/' . $n), 0755, true);
                    file_put_contents(BZ_ROOT . '/' . $n, (string) $zip->getFromIndex($i));
                }
            }
            $zip->close();
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['tablolar'])) {
            return 'Geçerli bir BOZKURT yedeği değil.';
        }
        $db = App::db();
        $db->pdo->beginTransaction();
        try {
            foreach (self::TABLES as $t) {
                if (!isset($data['tablolar'][$t])) {
                    continue;
                }
                if ($t === 'bz_kullanicilar' && !$data['tablolar'][$t]) {
                    continue; // kullanıcısız yedekle kilitlenmeyi önle
                }
                // Sütun adları izin listesinden: kötü niyetli yedek dosyasıyla SQL enjeksiyonu engellenir
                $cols = $db->columns($t);
                $db->q("DELETE FROM $t");
                foreach ($data['tablolar'][$t] as $row) {
                    if (is_array($row) && ($row = array_intersect_key($row, array_flip($cols)))) {
                        $db->insert($t, array_map(fn($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $row));
                    }
                }
            }
            $db->pdo->commit();
        } catch (\Throwable $e) {
            $db->pdo->rollBack();
            return 'Geri yükleme başarısız: ' . $e->getMessage();
        }
        Cache::clearAll();
        bz_log('yedek_geri_yukle', $data['tarih'] ?? '');
        return true;
    }
}
