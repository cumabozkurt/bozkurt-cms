<?php
declare(strict_types=1);

namespace Bozkurt;

/** Kurulumda isteğe bağlı örnek içerik */
final class Sample
{
    public static function seed(): void
    {
        $db = App::db();
        $home = Content::single('ana-sayfa');
        $veri = json_decode((string) $home['veri'], true) ?: [];
        $veri['ozellikler'] = [
            ['ikon' => '⚡', 'baslik' => 'Işık hızında', 'aciklama' => 'Tam sayfa önbellek ve WebP küçük resimlerle paylaşımlı hostingde bile anında açılır.'],
            ['ikon' => '🇹🇷', 'baslik' => 'Türkçe düşünür', 'aciklama' => 'Türkçe arayüz, doğru slug’lar, Türkçe tarih ve ₺ biçimi, KVKK araçları hazır.'],
            ['ikon' => '🛡️', 'baslik' => 'Güvenli', 'aciklama' => 'İki adımlı doğrulama, kaba kuvvet koruması, XSS temizleyici ve kilitli klasörler.'],
        ];
        $db->update('bz_icerik', ['veri' => json_encode($veri, JSON_UNESCAPED_UNICODE)], 'id = ?', [$home['id']]);

        $posts = [
            ['BOZKURT CMS yayında!', 'Duyuru', '<p>Tasarımcılar için Türkçe, hızlı ve güvenli içerik yönetim sistemi <strong>BOZKURT CMS</strong> ile tanışın.</p><h2>Neler var?</h2><p>Şablon etiketleri, tekrarlanan alanlar, medya kütüphanesi, formlar, KVKK araçları ve JSON API.</p><h2>Nasıl başlarım?</h2><p>Yönetim panelinden bu yazıyı düzenleyerek başlayabilirsiniz.</p>'],
            ['HTML şablonu 5 dakikada yönetilebilir yapmak', 'Rehber', '<p>Elinizdeki herhangi bir HTML tasarımı <code>sablonlar/</code> klasörüne koyun ve düzenlenecek yerleri <code>&lt;bz:alan&gt;</code> etiketiyle sarın.</p><h2>Örnek</h2><pre>&lt;h1&gt;&lt;bz:alan ad="baslik"&gt;Merhaba&lt;/bz:alan&gt;&lt;/h1&gt;</pre><p>Panel, alanı otomatik olarak tanır.</p>'],
            ['KVKK uyumlu iletişim formu nasıl kurulur?', 'Rehber', '<p><code>&lt;bz:form&gt;</code> etiketi spam koruması, açık rıza onay kutusu, kayıt ve e-posta bildirimini tek başına halleder.</p><h2>Saklama süresi</h2><p>Ayarlar bölümünden form verilerinin kaç gün saklanacağını belirleyin; süresi dolanlar otomatik silinir.</p>'],
        ];
        $t = time();
        foreach ($posts as $i => [$title, $cat, $html]) {
            $db->insert('bz_icerik', [
                'sablon' => 'blog', 'baslik' => $title, 'slug' => Str::slug($title), 'durum' => 'yayinda',
                'yayin_tarihi' => date('Y-m-d H:i:s', $t - $i * 86400 * 3),
                'veri' => json_encode(['kapak' => '', 'kategori' => $cat, 'yazar' => 'BOZKURT Ekibi', 'icerik' => $html, 'video' => ''], JSON_UNESCAPED_UNICODE),
                'seo' => '{}', 'sira' => 0, 'yazar_id' => 1, 'olusturma' => bz_now(), 'guncelleme' => bz_now(),
            ]);
        }
        foreach (['telefon' => '0312 000 00 00', 'eposta' => 'merhaba@ornek.com.tr', 'adres' => "Kızılay, Çankaya\nAnkara"] as $k => $v) {
            $db->upsert('bz_genel', ['anahtar' => $k, 'deger' => json_encode($v, JSON_UNESCAPED_UNICODE)], 'anahtar');
        }
    }
}
