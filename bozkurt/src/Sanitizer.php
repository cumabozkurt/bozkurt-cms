<?php
declare(strict_types=1);

namespace Bozkurt;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Zengin metin için izin listesi tabanlı HTML temizleyici (XSS koruması).
 */
final class Sanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'blockquote' => [], 'code' => [], 'pre' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'hr' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'figure' => [], 'figcaption' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'div' => [], 'span' => [],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'title'],
    ];
    private const IFRAME_HOSTS = ['www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com',
        'www.google.com', 'maps.google.com', 'open.spotify.com', 'www.dailymotion.com'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="bz-kok">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('bz-kok') ?? $doc->documentElement;
        if (!$root) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (!isset(self::TAGS[$tag])) {
                    // etiketi kaldır, içeriği koru
                    self::walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (!in_array($name, self::TAGS[$tag], true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true) && !self::safeUrl($attr->value, $tag)) {
                        $child->removeAttribute($attr->name);
                    }
                }
                if ($tag === 'iframe' && !$child->hasAttribute('src')) {
                    $node->removeChild($child);
                    continue;
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                if ($tag === 'img' && !$child->hasAttribute('loading')) {
                    $child->setAttribute('loading', 'lazy');
                }
                self::walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function safeUrl(string $url, string $tag): bool
    {
        // Tarayıcılar şema içindeki sekme/satır sonlarını ve baştaki kontrol karakterlerini yok sayar
        // ("java\tscript:"). Denetimden önce bunlar atılır.
        $url = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url)) ?? '';
        if ($tag === 'iframe') {
            $host = parse_url($url, PHP_URL_HOST);
            return str_starts_with($url, 'https://') && in_array($host, self::IFRAME_HOSTS, true);
        }
        if (preg_match('#^(https?:|mailto:|tel:|/|\#|\.\.?/)#i', $url)) {
            return true;
        }
        return !preg_match('#^[a-z0-9+.\-]+:#i', $url); // göreli URL'ler serbest, javascript: vb. yasak
    }
}
