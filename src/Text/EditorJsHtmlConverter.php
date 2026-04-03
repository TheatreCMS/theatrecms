<?php

/**
 * EditorJsHtmlConverter
 *
 * Converts Editor.js content (JSON string or decoded array) into safe HTML.
 *
 * This class accepts Editor.js payloads in one of three forms:
 * - a JSON string containing the full Editor.js object (with a top-level "blocks" array),
 * - a decoded array (either the full Editor.js object or a plain array of blocks),
 * - or a plain array/string value which will be coerced into a single paragraph.
 *
 * The converter supports the following Editor.js block types:
 * - paragraph (default)
 * - header
 * - list (ordered/unordered)
 * - quote
 * - image
 * - delimiter (renders an <hr />)
 * - checklist
 * - code
 *
 * Sanitization and safety:
 * - Inline tags allowed: b, strong, i, em, u, mark, code, del, s, br
 * - All other HTML is stripped. URLs are validated to be http(s) using PHP's
 *   FILTER_VALIDATE_URL and parsed scheme checks.
 * - Text content is escaped using htmlspecialchars after restoring the allowed
 *   inline tags, ensuring output is safe for inclusion in HTML.
 *
 * Usage example:
 * $converter = new EditorJsHtmlConverter();
 * $html = $converter->toHtml($editorJsJsonString);
 *
 * Note: This class focuses on producing markup suitable for rendering in a
 * typical CMS frontend. It intentionally keeps output formatting minimal and
 * trusts higher-level templates/styles to apply presentation.
 */

namespace TheatreCMS\Text;


class EditorJsHtmlConverter
{
    private const INLINE_TAGS = [
        'b', 'strong', 'i', 'em', 'u', 'mark', 'code', 'del', 's', 'br',
    ];

    /**
     * Convert an Editor.js payload to an HTML string.
     *
     * Accepts either a JSON string, a decoded array, or null. When given a
     * JSON string the method attempts to decode it and extract the `blocks`
     * array; when given an array it accepts either the full Editor.js object
     * (with `blocks`) or an array that already represents editor blocks. If the
     * payload cannot be decoded or contains no meaningful content an empty
     * string is returned.
     *
     * @param array|string|null $payload Editor.js payload (JSON string or decoded array)
     * @return string HTML representation (empty string when no content)
     */
    public function toHtml(array|string|null $payload): string
    {
        $blocks = $this->extractBlocks($payload);

        if ($blocks === []) {
            return '';
        }

        $html = [];
        foreach ($blocks as $block) {
            $rendered = $this->renderBlock($block);
            if ($rendered !== '') {
                $html[] = $rendered;
            }
        }

        return implode("\n", $html);
    }

    /**
     * Extract an array of Editor.js blocks from various payload shapes.
     *
     * - null => empty array
     * - JSON string => decoded and blocks extracted (if present)
     * - array => if contains `blocks` key, return that; if it already looks
     *   like a block array (each item is an array with a 'type') return it;
     *   otherwise coerce scalar segments into a single paragraph block.
     *
     * @param array|string|null $payload
     * @return array Array of blocks (each block is an associative array)
     */
    private function extractBlocks(array|string|null $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (is_string($payload)) {
            $trimmed = trim($payload);
            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (!empty($decoded['blocks']) && is_array($decoded['blocks'])) {
                        return $decoded['blocks'];
                    }
                    if ($this->isBlockArray($decoded)) {
                        return $decoded;
                    }
                }

                return [
                    [
                        'type' => 'paragraph',
                        'data' => ['text' => $trimmed],
                    ],
                ];
            }

            return [];
        }

        if (is_array($payload)) {
            if (!empty($payload['blocks']) && is_array($payload['blocks'])) {
                return $payload['blocks'];
            }

            if ($this->isBlockArray($payload)) {
                return $payload;
            }

            $text = implode("\n", array_map(static fn($segment) => is_scalar($segment) ? (string)$segment : '', $payload));
            if ($text === '') {
                return [];
            }

            return [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => $text],
                ],
            ];
        }

        return [];
    }

    /**
     * Determine whether the given array already represents an Editor.js
     * blocks array.
     *
     * The method returns "true" if the array is non-empty and every element
     * is an array that contains at least a 'type' key.
     *
     * @param array $payload
     * @return bool
     */
    private function isBlockArray(array $payload): bool
    {
        foreach ($payload as $item) {
            if (!is_array($item) || empty($item['type'])) {
                return false;
            }
        }

        return !empty($payload);
    }

    /**
     * Dispatch rendering for a single block.
     *
     * @param array $block Block array with keys 'type' and optional 'data'
     * @return string Rendered HTML for the block (empty string if unsupported/empty)
     */
    private function renderBlock(array $block): string
    {
        $type = $block['type'] ?? 'paragraph';
        $data = $block['data'] ?? [];

        return match ($type) {
            'header'    => $this->renderHeader($data),
            'list'      => $this->renderList($data),
            'quote'     => $this->renderQuote($data),
            'image'     => $this->renderImage($data),
            'delimiter'    => '<hr />',
            'sponsorBlock'  => '<div class="editorjs-sponsor-block"></div>',
            'scheduleBlock' => '<div class="editorjs-schedule-block"></div>',
            'checklist' => $this->renderChecklist($data),
            'code'      => $this->renderCode($data),
            default     => $this->renderParagraph($data),
        };
    }

    /**
     * Render a paragraph block.
     *
     * @param array $data Expecting ['text' => string]
     * @return string HTML <p> element or empty string
     */
    private function renderParagraph(array $data): string
    {
        $text = $this->sanitizeText($data['text'] ?? '');
        return $text === '' ? '' : sprintf('<p>%s</p>', $text);
    }

    /**
     * Render a header block.
     *
     * @param array $data Expecting ['level' => int, 'text' => string]
     * @return string HTML <h1>-<h6> or empty string
     */
    private function renderHeader(array $data): string
    {
        $level = max(1, min(6, (int)($data['level'] ?? 1)));
        $text = $this->sanitizeText($data['text'] ?? '');
        return $text === '' ? '' : sprintf('<h%d>%s</h%d>', $level, $text, $level);
    }

    /**
     * Render a list (ordered or unordered).
     *
     * @param array $data Expecting ['items' => array, 'style' => 'ordered'|'unordered']
     * @return string HTML <ol>/<ul> list or empty string
     */
    private function renderList(array $data): string
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return '';
        }

        $tag = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
        $lines = [];

        foreach ($items as $item) {
            $line = $this->sanitizeText((string)$item);
            if ($line === '') {
                continue;
            }
            $lines[] = sprintf('<li>%s</li>', $line);
        }

        return empty($lines) ? '' : sprintf('<%s>%s</%s>', $tag, implode('', $lines), $tag);
    }

    /**
     * Render a quote block.
     *
     * @param array $data Expecting ['text' => string, 'caption' => string|null]
     * @return string HTML blockquote or empty string
     */
    private function renderQuote(array $data): string
    {
        $text = $this->sanitizeText($data['text'] ?? '');
        if ($text === '') {
            return '';
        }

        $caption = $this->sanitizeText($data['caption'] ?? '');
        $body = sprintf('<p>%s</p>', $text);
        if ($caption !== '') {
            $body .= sprintf('<cite>%s</cite>', $caption);
        }

        return sprintf('<blockquote class="kg-card kg-callout-card kg-callout-card-blue">%s</blockquote>', $body);
    }

    /**
     * Render an image block.
     *
     * Validates the image URL; if invalid, returns an empty string. Caption and
     * image presentation flags (stretched, withBorder, withBackground) are
     * honored via CSS classes.
     *
     * @param array $data Expecting keys like 'file' (array with 'url'/'caption'), 'url', 'caption', 'stretched', 'withBorder', 'withBackground'
     * @return string HTML <figure> with <img> (and optional <figcaption>) or empty string
     */
    private function renderImage(array $data): string
    {
        $file = $data['file'] ?? [];
        $url = $this->sanitizeUrl($file['url'] ?? $data['url'] ?? '');
        if ($url === '') {
            return '';
        }

        $caption = $this->sanitizeText($data['caption'] ?? $file['caption'] ?? '');
        $classes = ['editorjs-image'];
        if (!empty($data['stretched'])) {
            $classes[] = 'editorjs-image--stretched';
        }
        if (!empty($data['withBorder'])) {
            $classes[] = 'editorjs-image--bordered';
        }
        if (!empty($data['withBackground'])) {
            $classes[] = 'editorjs-image--background';
        }

        $classAttr = implode(' ', $classes);
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escapedAlt = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');

        $figure = '<figure class="' . $classAttr . '"><img src="' . $escapedUrl . '" alt="' . $escapedAlt . '" loading="lazy" /></figure>';

        if ($caption !== '') {
            // Note: $caption is sanitized text (may contain a small set of inline tags);
            // keep the original behaviour of rendering the caption as HTML inside <figcaption>.
            $figure = '<figure class="' . $classAttr . '"><img src="' . $escapedUrl . '" alt="' . $escapedAlt . '" loading="lazy" /><figcaption>' . $caption . '</figcaption></figure>';
        }

        return $figure;
    }

    /**
     * Render a checklist block as a ul of items with a data-checked attribute.
     *
     * @param array $data Expecting ['items' => [['text' => string, 'checked' => bool], ...]]
     * @return string HTML unordered list or empty string
     */
    private function renderChecklist(array $data): string
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return '';
        }

        $lines = [];
        foreach ($items as $item) {
            $value = $this->sanitizeText($item['text'] ?? '');
            if ($value === '') {
                continue;
            }
            $checked = !empty($item['checked']);
            $classes = ['editorjs-checklist-item'];
            if ($checked) {
                $classes[] = 'editorjs-checklist-item--checked';
            }
            $lines[] = sprintf('<li class="%s" data-checked="%s">%s</li>', implode(' ', $classes), $checked ? 'true' : 'false', $value);
        }

        return empty($lines) ? '' : sprintf('<ul class="editorjs-checklist">%s</ul>', implode('', $lines));
    }

    /**
     * Render a code block inside a <pre><code> and escape its contents.
     *
     * @param array $data Expecting ['code' => string]
     * @return string HTML pre/code block or empty string
     */
    private function renderCode(array $data): string
    {
        $code = $data['code'] ?? '';
        if (!is_string($code) || $code === '') {
            return '';
        }

        $escaped = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        return sprintf('<pre><code>%s</code></pre>', $escaped);
    }

    /**
     * Sanitize a user-supplied text fragment.
     *
     * - Strips disallowed tags while retaining a small set of inline tags.
     * - Removes attributes and non-inline tags.
     * - Escapes the remaining content to prevent XSS, then restores the
     *   allowed inline tags.
     *
     * @param string $value Raw HTML/text fragment
     * @return string Sanitized text safe for inclusion inside block HTML
     */
    private function sanitizeText(string $value): string
    {
        $stripped = strip_tags($value, $this->allowedTags());
        $cleaned = preg_replace_callback(
            '/<\/?([a-z]+)[^>]*>/i',
            function (array $matches): string {
                $tag = strtolower($matches[1]);
                $closing = str_starts_with($matches[0], '</');
                if (!in_array($tag, self::INLINE_TAGS, true)) {
                    return '';
                }
                return $closing ? sprintf('</%s>', $tag) : sprintf('<%s>', $tag);
            },
            $stripped
        );

        $escaped = htmlspecialchars($cleaned, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim($this->restoreAllowedTags($escaped));
    }

    /**
     * Restore allowed inline tags that were escaped by htmlspecialchars.
     *
     * @param string $value
     * @return string
     */
    private function restoreAllowedTags(string $value): string
    {
        foreach (self::INLINE_TAGS as $tag) {
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

    /**
     * Build a strip_tags-allowed-tags string from the INLINE_TAGS constant.
     *
     * @return string e.g. '<b><strong><i>...'
     */
    private function allowedTags(): string
    {
        return '<' . implode('><', self::INLINE_TAGS) . '>';
    }

    /**
     * Validate and normalize a URL string. Only http/https schemes are allowed.
     *
     * @param string $value
     * @return string Normalized URL or empty string when invalid
     */
    private function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // FILTER_FLAG_SCHEME_REQUIRED was removed in PHP 8.0 — validate the URL
        // normally and perform an explicit scheme check below.
        $normalized = filter_var($value, FILTER_VALIDATE_URL);
        if ($normalized === false) {
            return '';
        }

        $scheme = parse_url($normalized, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return $normalized;
    }
}
