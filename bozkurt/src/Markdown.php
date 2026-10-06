<?php
declare(strict_types=1);

namespace Bozkurt;

/**
 * Bağımlılıksız Markdown ⇄ HTML.
 *  - toHtml: Markdown alan türü için (çıktı ayrıca Sanitizer'dan geçer)
 *  - fromHtml: sayfaların yapay zekâ dostu .md sürümleri ve llms-full.txt için
 */
final class Markdown
{
    public static function toHtml(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $blocks = preg_split('/\n{2,}/', trim($md)) ?: [];
        $out = [];
        $inCode = false;
        $code = '';
        foreach ($blocks as $b) {
            if ($inCode || str_starts_with($b, '```')) {
                $code .= ($inCode ? "\n\n" : '') . $b;
                $inCode = substr_count($code, '```') % 2 === 1;
                if (!$inCode) {
                    $body = preg_replace('/^```[a-z0-9]*\n?|\n?```$/i', '', $code) ?? '';
                    $out[] = '<pre><code>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</code></pre>';
                    $code = '';
                }
                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.+)$/', $b, $m)) {
                $lvl = max(2, strlen($m[1]));
                $out[] = "<h$lvl>" . self::inline($m[2]) . "</h$lvl>";
            } elseif (preg_match('/^(---|\*\*\*)$/', $b)) {
                $out[] = '<hr>';
            } elseif (preg_match('/^>\s?/', $b)) {
                $out[] = '<blockquote><p>' . self::inline(preg_replace('/^>\s?/m', '', $b) ?? '') . '</p></blockquote>';
            } elseif (preg_match('/^(\s*[-*+]\s+)/', $b)) {
                $items = preg_split('/\n\s*[-*+]\s+/', "\n" . $b) ?: [];
                $out[] = '<ul>' . implode('', array_map(fn($i) => '<li>' . self::inline(trim($i)) . '</li>', array_filter(array_map('trim', $items)))) . '</ul>';
            } elseif (preg_match('/^\s*\d+[.)]\s+/', $b)) {
                $items = preg_split('/\n\s*\d+[.)]\s+/', "\n" . $b) ?: [];
                $out[] = '<ol>' . implode('', array_map(fn($i) => '<li>' . self::inline(trim($i)) . '</li>', array_filter(array_map('trim', $items)))) . '</ol>';
            } else {
                $out[] = '<p>' . nl2br(self::inline($b), false) . '</p>';
            }
        }
        return Sanitizer::clean(implode("\n", $out));
    }

    private static function inline(string $t): string
    {
        $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t) ?? $t;
        $t = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1">', $t) ?? $t;
        $t = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $t) ?? $t;
        $t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t) ?? $t;
        $t = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\w)/s', '<em>$1</em>', $t) ?? $t;
        return $t;
    }

    public static function fromHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $map = [
            '#<h2[^>]*>(.*?)</h2>#is' => "\n\n## $1\n\n", '#<h3[^>]*>(.*?)</h3>#is' => "\n\n### $1\n\n", '#<h4[^>]*>(.*?)</h4>#is' => "\n\n#### $1\n\n",
            '#<(strong|b)>(.*?)</\1>#is' => '**$2**', '#<(em|i)>(.*?)</\1>#is' => '*$2*', '#<code>(.*?)</code>#is' => '`$1`',
            '#<a [^>]*href="([^"]*)"[^>]*>(.*?)</a>#is' => '[$2]($1)', '#<img [^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>#is' => '![$2]($1)',
            '#<img [^>]*src="([^"]*)"[^>]*>#is' => '![]($1)', '#<li[^>]*>(.*?)</li>#is' => "\n- $1", '#<blockquote[^>]*>(.*?)</blockquote>#is' => "\n\n> $1\n\n",
            '#<br\s*/?>#i' => "\n", '#</p>#i' => "\n\n", '#<hr\s*/?>#i' => "\n\n---\n\n", '#</(ul|ol)>#i' => "\n\n",
        ];
        foreach ($map as $re => $rep) {
            $html = preg_replace($re, $rep, $html) ?? $html;
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
