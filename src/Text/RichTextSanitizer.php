<?php

namespace TheatreCMS\Text;

/**
 * Sanitizes rich-text HTML (e.g. from the CKEditor5-powered person biography
 * field) down to a small safe whitelist, so stored content can never carry
 * more than that regardless of what a client sends — script tags,
 * event-handler attributes, unexpected elements, etc. are always stripped.
 *
 * Mirrors the tag-whitelisting approach in EditorJsHtmlConverter::sanitizeText().
 */
class RichTextSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i'];

    public static function toSafeHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $stripped = strip_tags($html, self::allowedTagsString());

        $cleaned = preg_replace_callback(
            '/<\/?([a-z]+)[^>]*>/i',
            static function (array $matches): string {
                $tag = strtolower($matches[1]);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    return '';
                }
                if ($tag === 'br') {
                    return '<br>';
                }
                $closing = str_starts_with($matches[0], '</');
                return $closing ? "</$tag>" : "<$tag>";
            },
            $stripped
        );

        // Body text only needs <, >, & escaped to prevent tag/entity
        // injection — quotes/apostrophes are left as literal characters since
        // this output is only ever rendered as HTML body content, never
        // embedded inside an attribute value.
        $decoded = html_entity_decode((string) $cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $escaped = htmlspecialchars($decoded, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return trim(self::restoreAllowedTags($escaped));
    }

    private static function restoreAllowedTags(string $value): string
    {
        foreach (self::ALLOWED_TAGS as $tag) {
            if ($tag === 'br') {
                $value = str_replace(['&lt;br&gt;', '&lt;br/&gt;'], '<br>', $value);
                continue;
            }

            $value = str_replace(
                ['&lt;' . $tag . '&gt;', '&lt;/' . $tag . '&gt;'],
                ["<$tag>", "</$tag>"],
                $value
            );
        }

        return $value;
    }

    private static function allowedTagsString(): string
    {
        return '<' . implode('><', self::ALLOWED_TAGS) . '>';
    }
}
