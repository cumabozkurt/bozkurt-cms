<?php
declare(strict_types=1);

namespace Bozkurt;

use PDO;
use PDOStatement;

/**
 * İnce PDO katmanı. SQLite (varsayılan, sıfır kurulum) ve MySQL/MariaDB (Hostinger) destekler.
 */
final class Db
{
    public PDO $pdo;
    public string $driver;

    public function __construct(array $cfg)
    {
        $this->driver = $cfg['db']['surucu'] ?? 'sqlite';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($this->driver === 'mysql') {
            $d = $cfg['db'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $d['sunucu'] ?? 'localhost', (int) ($d['port'] ?? 3306), $d['ad']);
            $this->pdo = new PDO($dsn, $d['kullanici'], $d['sifre'], $opts);
            $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci");
        } else {
            $file = $cfg['db']['dosya'] ?? (BZ_DATA . '/bozkurt.sqlite');
            $this->pdo = new PDO('sqlite:' . $file, null, null, $opts);
            $this->pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        }
    }

    public function q(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->q($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $r = $this->q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public function val(string $sql, array $params = []): mixed
    {
        $r = $this->q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' .
            implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->q($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
        return $this->q("UPDATE $table SET $set WHERE $where", [...array_values($data), ...$params])->rowCount();
    }

    public function upsert(string $table, array $data, string $key): void
    {
        $exists = $this->val("SELECT COUNT(*) FROM $table WHERE $key = ?", [$data[$key]]);
        if ($exists) {
            $k = $data[$key];
            unset($data[$key]);
            $this->update($table, $data, "$key = ?", [$k]);
        } else {
            $this->insert($table, $data);
        }
    }

    /** Tabloları oluşturur / günceller. Tekrar çalıştırmak güvenlidir. */
    public function migrate(): void
    {
        $my = $this->driver === 'mysql';
        $id = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $txt = $my ? 'MEDIUMTEXT' : 'TEXT';
        $str = fn(int $n) => $my ? "VARCHAR($n)" : 'TEXT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci' : '';

        $tables = [
            "CREATE TABLE IF NOT EXISTS bz_kullanicilar (
                id $id, ad {$str(120)} NOT NULL, eposta {$str(190)} NOT NULL UNIQUE,
                sifre {$str(255)} NOT NULL, rol {$str(20)} NOT NULL DEFAULT 'editor',
                totp_gizli {$str(64)} NULL, son_giris {$str(19)} NULL, olusturma {$str(19)} NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS bz_icerik (
                id $id, sablon {$str(100)} NOT NULL, baslik {$str(255)} NOT NULL DEFAULT '',
                slug {$str(190)} NOT NULL DEFAULT '', durum {$str(20)} NOT NULL DEFAULT 'taslak',
                yayin_tarihi {$str(19)} NOT NULL, veri $txt, seo $txt, sira INTEGER NOT NULL DEFAULT 0,
                yazar_id INTEGER NULL, olusturma {$str(19)} NOT NULL, guncelleme {$str(19)} NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS bz_surumler (
                id $id, icerik_id INTEGER NOT NULL, baslik {$str(255)}, veri $txt, seo $txt,
                kullanici_id INTEGER NULL, tarih {$str(19)} NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS bz_genel (anahtar {$str(100)} PRIMARY KEY, deger $txt)$tail",
            "CREATE TABLE IF NOT EXISTS bz_ayarlar (anahtar {$str(100)} PRIMARY KEY, deger $txt)$tail",
            "CREATE TABLE IF NOT EXISTS bz_medya (
                id $id, dosya {$str(255)} NOT NULL, orijinal {$str(255)}, mime {$str(100)},
                boyut INTEGER, genislik INTEGER, yukseklik INTEGER, alt {$str(255)}, tarih {$str(19)} NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS bz_formlar (
                id $id, form {$str(100)} NOT NULL, veri $txt, ip {$str(45)}, kvkk_onay INTEGER NOT NULL DEFAULT 0,
                okundu INTEGER NOT NULL DEFAULT 0, tarih {$str(19)} NOT NULL
            )$tail",
            "CREATE TABLE IF NOT EXISTS bz_giris_denemeleri (id $id, ip {$str(45)} NOT NULL, tarih {$str(19)} NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS bz_gunluk (
                id $id, kullanici_id INTEGER NULL, islem {$str(60)} NOT NULL, detay {$str(500)}, ip {$str(45)}, tarih {$str(19)} NOT NULL
            )$tail",
        ];
        foreach ($tables as $sql) {
            $this->pdo->exec($sql);
        }
        $more = [
            "CREATE TABLE IF NOT EXISTS bz_yonlendirmeler (id $id, kaynak {$str(255)} NOT NULL, hedef {$str(500)} NOT NULL, kod INTEGER NOT NULL DEFAULT 301, sayac INTEGER NOT NULL DEFAULT 0, tarih {$str(19)} NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS bz_404 (id $id, yol {$str(255)} NOT NULL, yonlendiren {$str(255)}, sayac INTEGER NOT NULL DEFAULT 1, son {$str(19)} NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS bz_tokenlar (id $id, ad {$str(120)} NOT NULL, token_hash {$str(64)} NOT NULL, kapsam {$str(20)} NOT NULL DEFAULT 'oku', son_kullanim {$str(19)} NULL, olusturma {$str(19)} NOT NULL)$tail",
            "CREATE TABLE IF NOT EXISTS bz_sifre_sifirlama (id $id, kullanici_id INTEGER NOT NULL, token_hash {$str(64)} NOT NULL, son {$str(19)} NOT NULL, kullanildi INTEGER NOT NULL DEFAULT 0)$tail",
            "CREATE TABLE IF NOT EXISTS bz_siparisler (id $id, siparis_no {$str(64)} NOT NULL, icerik_id INTEGER NULL, urun {$str(255)}, tutar {$str(20)} NOT NULL, para {$str(5)} NOT NULL DEFAULT 'TRY', durum {$str(20)} NOT NULL DEFAULT 'bekliyor', saglayici {$str(20)} NOT NULL, musteri $txt, ham $txt, tarih {$str(19)} NOT NULL, guncelleme {$str(19)} NOT NULL)$tail",
        ];
        foreach ($more as $sql) {
            $this->pdo->exec($sql);
        }
        $this->addColumn('bz_icerik', 'dil', $my ? "VARCHAR(5) NOT NULL DEFAULT 'tr'" : "TEXT NOT NULL DEFAULT 'tr'");
        $this->addColumn('bz_icerik', 'grup', 'INTEGER NOT NULL DEFAULT 0');
        $this->addColumn('bz_kullanicilar', 'totp_son', 'INTEGER NOT NULL DEFAULT 0');
        $this->addColumn('bz_kullanicilar', 'dil', $my ? "VARCHAR(5) NOT NULL DEFAULT 'tr'" : "TEXT NOT NULL DEFAULT 'tr'");
        $this->addColumn('bz_kullanicilar', 'oturum_surumu', 'INTEGER NOT NULL DEFAULT 0');
        try {
            $this->pdo->exec($my ? 'DROP INDEX idx_icerik_slug ON bz_icerik' : 'DROP INDEX IF EXISTS idx_icerik_slug');
        } catch (\PDOException) {
        }
        $indexes = [
            'CREATE INDEX idx_icerik_sablon ON bz_icerik (sablon, dil, durum, yayin_tarihi)',
            'CREATE UNIQUE INDEX idx_icerik_slug_dil ON bz_icerik (sablon, dil, slug)',
            'CREATE INDEX idx_icerik_grup ON bz_icerik (grup)',
            'CREATE INDEX idx_surum_icerik ON bz_surumler (icerik_id)',
            'CREATE INDEX idx_form ON bz_formlar (form, tarih)',
            'CREATE INDEX idx_giris_ip ON bz_giris_denemeleri (ip, tarih)',
            'CREATE UNIQUE INDEX idx_yonlendirme ON bz_yonlendirmeler (kaynak)',
            'CREATE UNIQUE INDEX idx_404 ON bz_404 (yol)',
            'CREATE UNIQUE INDEX idx_siparis_no ON bz_siparisler (siparis_no)',
            'CREATE INDEX idx_token ON bz_tokenlar (token_hash)',
        ];
        foreach ($indexes as $sql) {
            try {
                $this->pdo->exec($my ? $sql : str_replace('INDEX ', 'INDEX IF NOT EXISTS ', $sql));
            } catch (\PDOException) {
                // MySQL: indeks zaten var
            }
        }
    }

    /** Sütun yoksa ekler (sürüm yükseltmelerinde güvenli). */
    public function addColumn(string $table, string $col, string $def): void
    {
        if (in_array($col, $this->columns($table), true)) {
            return;
        }
        $this->pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
    }

    /** Tablonun sütun adları (yedekten geri yüklemede izin listesi olarak da kullanılır). */
    public function columns(string $table): array
    {
        if (!preg_match('/^bz_[a-z0-9_]+$/', $table)) {
            return [];
        }
        if ($this->driver === 'mysql') {
            return array_column($this->all('SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]), 'c');
        }
        return array_column($this->all("PRAGMA table_info($table)"), 'name');
    }
}
