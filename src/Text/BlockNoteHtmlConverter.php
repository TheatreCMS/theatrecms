<?php

namespace TheatreCMS\Text;

class BlockNoteHtmlConverter
{
    private const INLINE_TAGS = [
        'b', 'strong', 'i', 'em', 'u', 'mark', 'code', 'del', 's', 'br', 'a',
    ];

    public function toHtml(array|string|null $payload): string
    {
        $blocks = $this->extractBlocks($payload);

        if ($blocks === []) {
            return '';
        }

        $html = [];
        $i = 0;
        $count = count($blocks);

        while ($i < $count) {
            $type = $blocks[$i]['type'] ?? '';

            if (in_array($type, ['bulletListItem', 'numberedListItem'], true)) {
                $tag = $type === 'numberedListItem' ? 'ol' : 'ul';
                $items = [];
                while ($i < $count && ($blocks[$i]['type'] ?? '') === $type) {
                    $items[] = $this->renderListItem($blocks[$i]);
                    $i++;
                }
                $html[] = "<{$tag}>" . implode('', $items) . "</{$tag}>";
                continue;
            }

            if ($type === 'checkListItem') {
                $items = [];
                while ($i < $count && ($blocks[$i]['type'] ?? '') === 'checkListItem') {
                    $items[] = $this->renderCheckListItem($blocks[$i]);
                    $i++;
                }
                $html[] = '<ul class="bn-checklist">' . implode('', $items) . '</ul>';
                continue;
            }

            $rendered = $this->renderBlock($blocks[$i]);
            if ($rendered !== '') {
                $html[] = $rendered;
            }
            $i++;
        }

        return implode("\n", $html);
    }

    private function extractBlocks(array|string|null $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (is_string($payload)) {
            $trimmed = trim($payload);
            if ($trimmed === '' || $trimmed === '[]') {
                return [];
            }

            // Reject legacy EditorJS JSON (starts with '{') — return empty rather than crash
            if (str_starts_with($trimmed, '{')) {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && $this->isBlockArray($decoded)) {
                return $decoded;
            }

            return [];
        }

        if (is_array($payload)) {
            if ($this->isBlockArray($payload)) {
                return $payload;
            }
        }

        return [];
    }

    private function isBlockArray(array $payload): bool
    {
        if (empty($payload)) {
            return false;
        }
        foreach ($payload as $item) {
            if (!is_array($item) || empty($item['type'])) {
                return false;
            }
        }
        return true;
    }

    private function renderBlock(array $block): string
    {
        return match ($block['type'] ?? '') {
            'paragraph'     => $this->renderParagraph($block),
            'heading'       => $this->renderHeading($block),
            'quote'         => $this->renderQuote($block),
            'image'         => $this->renderImage($block),
            'codeBlock'     => $this->renderCodeBlock($block),
            'table'         => $this->renderTable($block),
            'divider'       => $this->renderDivider(),
            'callout'       => $this->renderCallout($block),
            'imageGallery'  => $this->renderImageGallery($block),
            'sponsorBlock'  => $this->renderSponsorBlock($block),
            'scheduleBlock' => $this->renderScheduleBlock(),
            'columns'       => $this->renderColumns($block),
            default         => $this->renderParagraph($block),
        };
    }

    private function renderParagraph(array $block): string
    {
        $content = $this->renderInlineContent($block['content'] ?? []);
        if ($content === '') {
            return '';
        }
        $align = $block['props']['textAlignment'] ?? 'left';
        $style = $align !== 'left' ? sprintf(' style="text-align:%s"', htmlspecialchars($align, ENT_QUOTES, 'UTF-8')) : '';
        return sprintf('<p%s>%s</p>', $style, $content);
    }

    private function renderHeading(array $block): string
    {
        $level = max(1, min(6, (int)($block['props']['level'] ?? 1)));
        $content = $this->renderInlineContent($block['content'] ?? []);
        return $content === '' ? '' : sprintf('<h%d>%s</h%d>', $level, $content, $level);
    }

    private function renderQuote(array $block): string
    {
        $content = $this->renderInlineContent($block['content'] ?? []);
        return $content === '' ? '' : sprintf('<blockquote class="bn-quote"><p>%s</p></blockquote>', $content);
    }

    private function renderImage(array $block): string
    {
        $props = $block['props'] ?? [];
        $url = $this->sanitizeUrl($props['url'] ?? '');
        if ($url === '') {
            return '';
        }
        $caption = $this->sanitizeText($props['caption'] ?? '');
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $escapedAlt = htmlspecialchars(strip_tags($caption), ENT_QUOTES, 'UTF-8');

        if ($caption !== '') {
            return sprintf(
                '<figure class="bn-image"><img src="%s" alt="%s" loading="lazy" /><figcaption>%s</figcaption></figure>',
                $escapedUrl,
                $escapedAlt,
                $caption
            );
        }
        return sprintf('<figure class="bn-image"><img src="%s" alt="%s" loading="lazy" /></figure>', $escapedUrl, $escapedAlt);
    }

    private function renderCodeBlock(array $block): string
    {
        $content = $block['content'] ?? [];
        $code = is_array($content) && isset($content[0]['text']) ? $content[0]['text'] : '';
        if ($code === '') {
            return '';
        }
        return sprintf('<pre><code>%s</code></pre>', htmlspecialchars($code, ENT_QUOTES, 'UTF-8'));
    }

    private function renderTable(array $block): string
    {
        $tableContent = $block['content'] ?? [];
        $rows = is_array($tableContent) ? ($tableContent['rows'] ?? []) : [];
        if (empty($rows)) {
            return '';
        }

        $rowHtml = [];
        foreach ($rows as $row) {
            $cells = $row['cells'] ?? [];
            $cellHtml = [];
            foreach ($cells as $cell) {
                $cellContent = is_array($cell) ? $this->renderInlineContent($cell) : '';
                $cellHtml[] = sprintf('<td>%s</td>', $cellContent);
            }
            $rowHtml[] = sprintf('<tr>%s</tr>', implode('', $cellHtml));
        }

        return sprintf('<table class="bn-table"><tbody>%s</tbody></table>', implode('', $rowHtml));
    }

    private function renderDivider(): string
    {
        return '<hr />';
    }

    private function renderListItem(array $block): string
    {
        $content = $this->renderInlineContent($block['content'] ?? []);
        return sprintf('<li>%s</li>', $content);
    }

    private function renderCheckListItem(array $block): string
    {
        $checked = !empty($block['props']['checked']);
        $content = $this->renderInlineContent($block['content'] ?? []);
        $classes = 'bn-checklist-item' . ($checked ? ' bn-checklist-item--checked' : '');
        return sprintf(
            '<li class="%s" data-checked="%s">%s</li>',
            $classes,
            $checked ? 'true' : 'false',
            $content
        );
    }

    private function renderCallout(array $block): string
    {
        $props = $block['props'] ?? [];
        $bg = htmlspecialchars($props['backgroundColor'] ?? '#FFF8E7', ENT_QUOTES, 'UTF-8');
        $border = htmlspecialchars($props['borderColor'] ?? '#F59E0B', ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars($props['textColor'] ?? '#92400E', ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars($props['icon'] ?? '', ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($props['label'] ?? '', ENT_QUOTES, 'UTF-8');
        $body = $this->renderInlineContent($block['content'] ?? []);

        $header = '';
        if ($icon !== '' || $label !== '') {
            $header = sprintf(
                '<div class="bn-callout__header"><span class="bn-callout__icon">%s</span><span class="bn-callout__label">%s</span></div>',
                $icon,
                $label
            );
        }

        return sprintf(
            '<div class="bn-callout" style="background:%s;border-left:4px solid %s;color:%s">%s<div class="bn-callout__body">%s</div></div>',
            $bg,
            $border,
            $color,
            $header,
            $body
        );
    }

    private function renderImageGallery(array $block): string
    {
        $props = $block['props'] ?? [];
        $itemsJson = $props['items'] ?? '[]';
        $items = json_decode((string)$itemsJson, true);
        if (!is_array($items) || $items === []) {
            return '';
        }

        $layout = in_array($props['layout'] ?? '', ['grid', 'list'], true) ? $props['layout'] : 'grid';
        $figures = [];
        foreach ($items as $item) {
            $url = $this->sanitizeUrl($item['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $caption = $this->sanitizeText($item['caption'] ?? '');
            $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $escapedAlt = htmlspecialchars(strip_tags($caption), ENT_QUOTES, 'UTF-8');
            $fig = sprintf('<figure class="bn-gallery__item"><img src="%s" alt="%s" loading="lazy" />', $escapedUrl, $escapedAlt);
            if ($caption !== '') {
                $fig .= sprintf('<figcaption>%s</figcaption>', $caption);
            }
            $fig .= '</figure>';
            $figures[] = $fig;
        }

        if ($figures === []) {
            return '';
        }

        $gallery = sprintf(
            '<div class="bn-gallery" data-layout="%s">%s</div>',
            htmlspecialchars($layout, ENT_QUOTES, 'UTF-8'),
            implode('', $figures)
        );

        $caption = $this->sanitizeText($props['caption'] ?? '');
        if ($caption !== '') {
            return sprintf('<figure class="bn-gallery-wrap">%s<figcaption>%s</figcaption></figure>', $gallery, $caption);
        }

        return $gallery;
    }

    private function renderSponsorBlock(array $block): string
    {
        $props = $block['props'] ?? [];
        $name = htmlspecialchars($props['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $logoUrl = $this->sanitizeUrl($props['logoUrl'] ?? '');
        $websiteUrl = $this->sanitizeUrl($props['websiteUrl'] ?? '');

        $inner = '';
        if ($logoUrl !== '') {
            $inner .= sprintf('<img src="%s" alt="%s" class="bn-sponsor__logo" />', htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'), $name);
        }
        if ($name !== '') {
            $inner .= sprintf('<span class="bn-sponsor__name">%s</span>', $name);
        }

        $content = $inner !== '' ? $inner : '';
        if ($websiteUrl !== '' && $content !== '') {
            $content = sprintf('<a href="%s" class="bn-sponsor__link">%s</a>', htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8'), $content);
        }

        return sprintf('<div class="bn-sponsor-block">%s</div>', $content);
    }

    private function renderScheduleBlock(): string
    {
        return '<div class="bn-schedule-block"></div>';
    }

    private function renderColumns(array $block): string
    {
        $children = $block['children'] ?? [];
        if (empty($children)) {
            return '';
        }

        $columns = [];
        foreach ($children as $child) {
            if (($child['type'] ?? '') === 'columnBlock') {
                $innerBlocks = $child['children'] ?? [];
                $innerHtml = $this->toHtml($innerBlocks);
                $columns[] = sprintf('<div class="bn-column">%s</div>', $innerHtml);
            }
        }

        if ($columns === []) {
            return '';
        }

        $count = count($columns);
        return sprintf('<div class="bn-columns" data-cols="%d">%s</div>', $count, implode('', $columns));
    }

    private function renderInlineContent(array $content): string
    {
        if (empty($content)) {
            return '';
        }

        $result = '';
        foreach ($content as $item) {
            $type = $item['type'] ?? '';

            if ($type === 'text') {
                $text = htmlspecialchars($item['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $styles = $item['styles'] ?? [];
                if (!empty($styles['code'])) {
                    $text = sprintf('<code>%s</code>', $text);
                }
                if (!empty($styles['strikethrough'])) {
                    $text = sprintf('<s>%s</s>', $text);
                }
                if (!empty($styles['underline'])) {
                    $text = sprintf('<u>%s</u>', $text);
                }
                if (!empty($styles['italic'])) {
                    $text = sprintf('<em>%s</em>', $text);
                }
                if (!empty($styles['bold'])) {
                    $text = sprintf('<strong>%s</strong>', $text);
                }
                $result .= $text;
            } elseif ($type === 'link') {
                $href = $this->sanitizeUrl($item['href'] ?? '');
                $inner = $this->renderInlineContent($item['content'] ?? []);
                if ($href !== '' && $inner !== '') {
                    $result .= sprintf('<a href="%s">%s</a>', htmlspecialchars($href, ENT_QUOTES, 'UTF-8'), $inner);
                } else {
                    $result .= $inner;
                }
            }
        }

        return $result;
    }

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
                if ($tag === 'a') {
                    if ($closing) {
                        return '</a>';
                    }
                    if (
                        preg_match('/\bhref\s*=\s*"([^"]*)"/', $matches[0], $hrefMatch) ||
                        preg_match("/\\bhref\\s*=\\s*'([^']*)'/", $matches[0], $hrefMatch)
                    ) {
                        $href = html_entity_decode($hrefMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $safeHref = $this->sanitizeUrl($href);
                        if ($safeHref !== '') {
                            return sprintf('<a href="%s">', $safeHref);
                        }
                    }
                    return '';
                }
                return $closing ? sprintf('</%s>', $tag) : sprintf('<%s>', $tag);
            },
            $stripped
        );

        $decoded = html_entity_decode((string) $cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $escaped = htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return trim($this->restoreAllowedTags($escaped));
    }

    private function restoreAllowedTags(string $value): string
    {
        foreach (self::INLINE_TAGS as $tag) {
            if ($tag === 'br') {
                $value = str_replace(['&lt;br&gt;', '&lt;br/&gt;'], '<br>', $value);
                continue;
            }

            if ($tag === 'a') {
                $value = (string) preg_replace('/&lt;a href=&quot;(.+?)&quot;&gt;/', '<a href="$1">', $value);
                $value = str_replace('&lt;/a&gt;', '</a>', $value);
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

    private function allowedTags(): string
    {
        return '<' . implode('><', self::INLINE_TAGS) . '>';
    }

    private function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

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
