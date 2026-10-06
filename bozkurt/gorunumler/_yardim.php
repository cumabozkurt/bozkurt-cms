<?php
declare(strict_types=1);

use Bozkurt\App;
use Bozkurt\Content;

if (!function_exists('bz_ikon')) {
    /** Satır içi SVG ikonlar (harici bağımlılık yok) */
    function bz_ikon(string $n, int $s = 18): string
    {
        $p = [
            'panel' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'sayfa' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
            'yigin' => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/><path d="m3 17 9 5 9-5" opacity=".5"/>',
            'ev' => '<path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
            'kalem' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/>',
            'medya' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
            'form' => '<path d="M4 4h16v16H4z"/><path d="m4 7 8 6 8-6"/>',
            'dunya' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
            'kullanici' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'ayar' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'yedek' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
            'gunluk' => '<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>',
            'cikis' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
            'arti' => '<path d="M12 5v14M5 12h14"/>',
            'goz' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
            'cop' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
            'ay' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
            'sistem' => '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'yukari' => '<path d="m18 15-6-6-6 6"/>',
            'asagi' => '<path d="m6 9 6 6 6-6"/>',
            'arama' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'yildiz' => '<path d="M12 3l1.9 5.8L20 10l-5 3.6 1.9 5.9L12 16l-4.9 3.5L9 13.6 4 10l6.1-1.2z"/>',
            'takvim' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>',
            'sepet' => '<path d="M3 4h2l2.5 11h11L21 7H6.5"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>',
            'anahtar' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
            'arac' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
            'yon' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'grafik' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/>',
        ][$n] ?? '<circle cx="12" cy="12" r="9"/>';
        return '<svg class="ikon" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }

    /** Alan tanımına göre yönetim formu girdisi üretir */
    function bz_alan(array $def, mixed $value, string $name, array $errors = [], string $errKey = ''): string
    {
        $id = 'a_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $err = $errors[$errKey] ?? null;
        $req = $def['zorunlu'] ? ' required' : '';
        $ph = $def['yer_tutucu'] !== '' ? ' placeholder="' . e($def['yer_tutucu']) . '"' : '';
        $val = is_array($value) ? '' : (string) $value;
        $h = '<div class="alan alan-' . e($def['tur']) . ($def['genislik'] === 'yarim' ? ' yarim' : '') . ($err ? ' hatali' : '') . '">';
        if ($def['tur'] !== 'onay') {
            $h .= '<label for="' . $id . '">' . e($def['etiket']) . ($def['zorunlu'] ? ' <b class="zorunlu">*</b>' : '') . '</label>';
        }
        switch ($def['tur']) {
            case 'uzunmetin':
            case 'harita':
                $h .= '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . ($def['tur'] === 'harita' ? 2 : 4) . '"' . $req . $ph . '>' . e($val) . '</textarea>';
                break;
            case 'markdown':
                $h .= '<textarea id="' . $id . '" name="' . e($name) . '" rows="12" class="kod-alan" data-yz-hedef' . $req . ' placeholder="## Başlık&#10;&#10;**kalın**, *italik*, [bağlantı](https://…)">' . e($val) . '</textarea>';
                break;
            case 'il':
                $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— İl seçin —</option>';
                foreach (\Bozkurt\Validate::ILLER as $il) {
                    $h .= '<option' . ($il === $val ? ' selected' : '') . '>' . e($il) . '</option>';
                }
                $h .= '</select>';
                break;
            case 'tckn':
            case 'vkn':
                $h .= '<input type="text" inputmode="numeric" autocomplete="off" maxlength="' . ($def['tur'] === 'tckn' ? 11 : 10) . '" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . '>';
                break;
            case 'iban':
                $h .= '<input type="text" id="' . $id . '" name="' . e($name) . '" value="' . e(trim(chunk_split($val, 4, ' '))) . '"' . $req . ' placeholder="TR00 0000 0000 0000 0000 0000 00" autocomplete="off">';
                break;
            case 'bloklar':
                $rows = is_array($value) ? array_values($value) : [];
                $h .= '<div class="tekrar bloklar" data-tekrar="' . e($name) . '"><div class="tekrar-satirlar">';
                foreach ($rows as $i => $row) {
                    $tip = (string) ($row['_tip'] ?? '');
                    if (isset($def['bloklar'][$tip])) {
                        $h .= bz_blok_satir($def, $tip, $row, $name, (string) $i, $errors);
                    }
                }
                $h .= '</div><div class="blok-ekle">';
                foreach ($def['bloklar'] ?? [] as $tip => $b) {
                    $h .= '<template data-blok="' . e($tip) . '">' . bz_blok_satir($def, $tip, [], $name, '__i__', []) . '</template>' .
                        '<button type="button" class="dugme kucuk hayalet" data-blok-ekle="' . e($tip) . '">' . bz_ikon('arti', 14) . ' ' . e($b['etiket']) . '</button>';
                }
                $h .= '</div></div>';
                break;
            case 'zengin':
                $h .= '<div class="editor" data-editor><div class="editor-arac" role="toolbar">' .
                    '<button type="button" data-k="formatBlock" data-v="p" title="Paragraf">¶</button>' .
                    '<button type="button" data-k="formatBlock" data-v="h2" title="Başlık 2">H2</button>' .
                    '<button type="button" data-k="formatBlock" data-v="h3" title="Başlık 3">H3</button>' .
                    '<button type="button" data-k="bold" title="Kalın (Ctrl+B)"><b>B</b></button>' .
                    '<button type="button" data-k="italic" title="İtalik (Ctrl+I)"><i>I</i></button>' .
                    '<button type="button" data-k="underline" title="Altı çizili"><u>U</u></button>' .
                    '<button type="button" data-k="insertUnorderedList" title="Madde listesi">• —</button>' .
                    '<button type="button" data-k="insertOrderedList" title="Numaralı liste">1.</button>' .
                    '<button type="button" data-k="formatBlock" data-v="blockquote" title="Alıntı">❝</button>' .
                    '<button type="button" data-k="link" title="Bağlantı">🔗</button>' .
                    '<button type="button" data-k="resim" title="Medyadan resim ekle">🖼</button>' .
                    '<button type="button" data-k="video" title="YouTube videosu">▶</button>' .
                    '<button type="button" data-k="removeFormat" title="Biçimi temizle">⌫</button>' .
                    '<button type="button" data-k="kaynak" title="HTML kaynağı">&lt;/&gt;</button>' .
                    '<button type="button" data-k="yz" title="Yapay zekâ yardımcısı" class="yz-dugme">✨ YZ</button>' .
                    '<span class="kelime-sayaci" data-kelime></span>' .
                    '</div><div class="editor-alan metin" contenteditable="true" id="' . $id . '">' . $val . '</div>' .
                    '<textarea name="' . e($name) . '" hidden>' . e($val) . '</textarea></div>';
                break;
            case 'resim':
            case 'dosya':
                $url = bz_upload_url($val);
                $isImg = $def['tur'] === 'resim';
                $h .= '<div class="medya-sec" data-medya-sec data-tur="' . ($isImg ? 'resim' : '') . '">' .
                    '<div class="medya-onizleme">' . ($val !== '' ? ($isImg ? '<img src="' . e($url) . '" alt="">' : '<span>📎 ' . e(basename($val)) . '</span>') : '<span class="bos-medya">' . ($isImg ? 'Resim seçilmedi' : 'Dosya seçilmedi') . '</span>') . '</div>' .
                    '<input type="hidden" name="' . e($name) . '" value="' . e($val) . '">' .
                    '<div class="medya-dugmeler"><button type="button" class="dugme kucuk" data-medya-ac>' . ($isImg ? 'Resim seç / yükle' : 'Dosya seç / yükle') . '</button>' .
                    '<button type="button" class="dugme kucuk hayalet" data-medya-temizle>Kaldır</button></div></div>';
                break;
            case 'tarih':
                $h .= '<input type="date" id="' . $id . '" name="' . e($name) . '" value="' . e($val ? substr($val, 0, 10) : '') . '"' . $req . '>';
                break;
            case 'tarihsaat':
                $h .= '<input type="datetime-local" id="' . $id . '" name="' . e($name) . '" value="' . e($val ? date('Y-m-d\TH:i', strtotime($val)) : '') . '"' . $req . '>';
                break;
            case 'sayi':
            case 'fiyat':
                $h .= '<div class="' . ($def['tur'] === 'fiyat' ? 'onek-kutu' : '') . '">' . ($def['tur'] === 'fiyat' ? '<span>₺</span>' : '') .
                    '<input type="text" inputmode="decimal" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . $ph . '></div>';
                break;
            case 'secim':
                $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— Seçin —</option>';
                foreach (Content::optionPairs($def['secenekler']) as $v => $l) {
                    $h .= '<option value="' . e($v) . '"' . ($v === $val ? ' selected' : '') . '>' . e($l) . '</option>';
                }
                $h .= '</select>';
                break;
            case 'coklusecim':
                $sel = is_array($value) ? $value : [];
                $h .= '<div class="secenekler">';
                foreach (Content::optionPairs($def['secenekler']) as $v => $l) {
                    $h .= '<label class="cip"><input type="checkbox" name="' . e($name) . '[]" value="' . e($v) . '"' . (in_array($v, $sel, true) ? ' checked' : '') . '> ' . e($l) . '</label>';
                }
                $h .= '</div>';
                break;
            case 'onay':
                $h .= '<label class="anahtar"><input type="checkbox" name="' . e($name) . '" value="1"' . ($val ? ' checked' : '') . '><span></span> ' . e($def['etiket']) . '</label>';
                break;
            case 'renk':
                $h .= '<input type="color" id="' . $id . '" name="' . e($name) . '" value="' . e($val ?: '#b91c1c') . '">';
                break;
            case 'iliski':
                $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— Seçin —</option>';
                if ($def['sablon'] && Content::template($def['sablon'])) {
                    foreach (App::db()->all("SELECT id, baslik FROM bz_icerik WHERE sablon = ? AND slug != '' ORDER BY baslik LIMIT 500", [$def['sablon']]) as $r) {
                        $h .= '<option value="' . $r['id'] . '"' . ((string) $r['id'] === $val ? ' selected' : '') . '>' . e($r['baslik']) . '</option>';
                    }
                }
                $h .= '</select>';
                break;
            case 'tekrar':
                $rows = is_array($value) ? array_values($value) : [];
                $h .= '<div class="tekrar" data-tekrar="' . e($name) . '"><div class="tekrar-satirlar">';
                foreach ($rows as $i => $row) {
                    $h .= bz_tekrar_satir($def, $row, $name, (string) $i, $errors, $errKey);
                }
                $h .= '</div><template>' . bz_tekrar_satir($def, [], $name, '__i__', [], $errKey) . '</template>' .
                    '<button type="button" class="dugme kucuk hayalet" data-tekrar-ekle>' . bz_ikon('arti', 16) . ' Satır ekle</button></div>';
                break;
            case 'eposta':
                $h .= '<input type="email" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . $ph . '>';
                break;
            case 'url':
            case 'video':
                $h .= '<input type="url" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . ($ph ?: ' placeholder="https://"') . '>';
                break;
            case 'telefon':
                $h .= '<input type="tel" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . ' placeholder="05xx xxx xx xx">';
                break;
            default:
                $h .= '<input type="text" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . $ph .
                    ($def['en_fazla'] ? ' maxlength="' . (int) $def['en_fazla'] . '"' : '') . '>';
        }
        if ($def['yardim'] !== '') {
            $h .= '<small class="yardim">' . e($def['yardim']) . '</small>';
        }
        if ($err) {
            $h .= '<small class="hata-metni">' . e($err) . '</small>';
        }
        return $h . '</div>';
    }

    function bz_tekrar_satir(array $def, array $row, string $name, string $i, array $errors, string $errKey): string
    {
        $h = '<div class="tekrar-satir"><div class="tekrar-tutamac"><span>⋮⋮</span>' .
            '<button type="button" data-tekrar-yukari title="Yukarı">' . bz_ikon('yukari', 14) . '</button>' .
            '<button type="button" data-tekrar-asagi title="Aşağı">' . bz_ikon('asagi', 14) . '</button>' .
            '<button type="button" data-tekrar-sil title="Sil">' . bz_ikon('cop', 14) . '</button></div><div class="tekrar-alanlar">';
        foreach ($def['alt'] ?? [] as $sad => $sdef) {
            $h .= bz_alan($sdef, $row[$sad] ?? '', $name . '[' . $i . '][' . $sad . ']', $errors, $def['ad'] . '.' . $i . '.' . $sad);
        }
        return $h . '</div></div>';
    }

    function bz_blok_satir(array $def, string $tip, array $row, string $name, string $i, array $errors): string
    {
        $b = $def['bloklar'][$tip];
        $h = '<div class="tekrar-satir blok-satir"><div class="tekrar-tutamac"><span>⋮⋮</span>' .
            '<button type="button" data-tekrar-yukari title="Yukarı">' . bz_ikon('yukari', 14) . '</button>' .
            '<button type="button" data-tekrar-asagi title="Aşağı">' . bz_ikon('asagi', 14) . '</button>' .
            '<button type="button" data-tekrar-sil title="Sil">' . bz_ikon('cop', 14) . '</button></div><div class="tekrar-alanlar">' .
            '<div class="blok-baslik">' . e($b['etiket']) . '</div><input type="hidden" name="' . e($name) . '[' . $i . '][_tip]" value="' . e($tip) . '">';
        foreach ($b['alt'] ?? [] as $sad => $sdef) {
            $h .= bz_alan($sdef, $row[$sad] ?? '', $name . '[' . $i . '][' . $sad . ']', $errors, $def['ad'] . '.' . $i . '.' . $sad);
        }
        return $h . '</div></div>';
    }

    /** Alanları "grup" niteliğine göre sekmelere/kartlara ayırır */
    function bz_alan_gruplari(array $defs): array
    {
        $g = [];
        foreach ($defs as $ad => $d) {
            $g[$d['grup'] ?: 'İçerik'][$ad] = $d;
        }
        return $g;
    }
}
