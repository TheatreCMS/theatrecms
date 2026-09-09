<?php

namespace TheatreCMS\Text;

/**
 * Plain-text helpers shared by anything that needs to reduce HTML down to
 * text-only content for a non-HTML context (schema.org descriptions, SEO
 * meta tags).
 */
class PlainText
{
    /**
     * Convert an HTML fragment (e.g. a biography saved by the rich-text
     * person editor, or Editor.js output) into plain text.
     */
    public static function fromHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Turn block/line boundaries into newlines before stripping tags, so
        // separate paragraphs/list items/headings don't run together.
        $text = preg_replace('/<(br)\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(p|h[1-6]|li|blockquote|pre)>/i', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]*\n+/', "\n\n", (string) $text);

        return trim((string) $text);
    }

    /**
     * Truncate plain text to at most `$length` characters, breaking on a
     * word boundary and appending an ellipsis when truncation occurred.
     */
    public static function truncate(string $text, int $length): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " \t\n\r\0\x0B.,;:-") . '…';
    }
}
