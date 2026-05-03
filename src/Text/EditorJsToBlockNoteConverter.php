<?php

namespace TheatreCMS\Text;

class EditorJsToBlockNoteConverter
{
    public function convert(string $editorJsJson): string
    {
        $decoded = json_decode(trim($editorJsJson), true);
        if (!is_array($decoded)) {
            return '[]';
        }

        // Accept both full EditorJS object {blocks:[…]} and a bare blocks array
        if (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
            $editorJsBlocks = $decoded['blocks'];
        } elseif ($this->isBlockArray($decoded)) {
            $editorJsBlocks = $decoded;
        } else {
            return '[]';
        }

        $result = [];
        foreach ($editorJsBlocks as $block) {
            foreach ($this->convertBlock($block) as $bn) {
                $result[] = $bn;
            }
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    /** @return array<int, array<string, mixed>> Zero or more BlockNote block arrays */
    private function convertBlock(array $block): array
    {
        $type = $block['type'] ?? 'paragraph';
        $data = $block['data'] ?? [];

        return match ($type) {
            'paragraph'     => [$this->convertParagraph($data)],
            'header'        => [$this->convertHeader($data)],
            'list'          => $this->convertList($data),
            'quote'         => $this->convertQuote($data),
            'image'         => [$this->convertImage($data)],
            'imageGallery'  => [$this->convertImageGallery($data)],
            'code'          => [$this->convertCode($data)],
            'delimiter'     => [$this->convertDelimiter()],
            'checklist'     => $this->convertChecklist($data),
            'table'         => [$this->convertTable($data)],
            'callout'       => [$this->convertCallout($data)],
            'sponsorBlock'  => [$this->convertSponsorBlock()],
            'scheduleBlock' => [$this->convertScheduleBlock()],
            'columns'       => [$this->convertColumns($data)],
            default         => [$this->convertParagraph($data)],
        };
    }

    private function convertParagraph(array $data): array
    {
        return [
            'id'       => $this->generateId(),
            'type'     => 'paragraph',
            'props'    => $this->defaultProps(),
            'content'  => $this->htmlToInlineContent($data['text'] ?? ''),
            'children' => [],
        ];
    }

    private function convertHeader(array $data): array
    {
        return [
            'id'       => $this->generateId(),
            'type'     => 'heading',
            'props'    => $this->defaultProps(['level' => max(1, min(3, (int)($data['level'] ?? 1)))]),
            'content'  => $this->htmlToInlineContent($data['text'] ?? ''),
            'children' => [],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function convertList(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return [];
        }

        $type = ($data['style'] ?? 'unordered') === 'ordered' ? 'numberedListItem' : 'bulletListItem';
        $blocks = [];
        foreach ($items as $item) {
            $text = is_string($item) ? $item : ($item['content'] ?? '');
            $blocks[] = [
                'id'       => $this->generateId(),
                'type'     => $type,
                'props'    => $this->defaultProps(),
                'content'  => $this->htmlToInlineContent($text),
                'children' => [],
            ];
        }
        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function convertQuote(array $data): array
    {
        $blocks = [];
        $blocks[] = [
            'id'       => $this->generateId(),
            'type'     => 'quote',
            'props'    => $this->defaultProps(),
            'content'  => $this->htmlToInlineContent($data['text'] ?? ''),
            'children' => [],
        ];

        $caption = trim(strip_tags($data['caption'] ?? ''));
        if ($caption !== '') {
            $blocks[] = [
                'id'       => $this->generateId(),
                'type'     => 'paragraph',
                'props'    => $this->defaultProps(),
                'content'  => [['type' => 'text', 'text' => "\u{2014} " . $caption, 'styles' => $this->emptyStyles()]],
                'children' => [],
            ];
        }
        return $blocks;
    }

    private function convertImage(array $data): array
    {
        $file = $data['file'] ?? [];
        $url = $file['url'] ?? $data['url'] ?? '';
        $caption = $data['caption'] ?? $file['caption'] ?? '';

        return [
            'id'       => $this->generateId(),
            'type'     => 'image',
            'props'    => $this->defaultProps([
                'url'     => $url,
                'caption' => is_string($caption) ? strip_tags($caption) : '',
                'width'   => '100%',
            ]),
            'content'  => [],
            'children' => [],
        ];
    }

    private function convertImageGallery(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $normalized[] = [
                'url'     => $item['url'] ?? '',
                'caption' => strip_tags($item['caption'] ?? ''),
            ];
        }

        $layout = in_array($data['layout'] ?? '', ['grid', 'list'], true) ? $data['layout'] : 'grid';

        return [
            'id'       => $this->generateId(),
            'type'     => 'imageGallery',
            'props'    => $this->defaultProps([
                'layout'  => $layout,
                'caption' => strip_tags($data['caption'] ?? ''),
                'items'   => json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]),
            'content'  => [],
            'children' => [],
        ];
    }

    private function convertCode(array $data): array
    {
        $code = $data['code'] ?? '';
        return [
            'id'       => $this->generateId(),
            'type'     => 'codeBlock',
            'props'    => $this->defaultProps(['language' => $data['language'] ?? '']),
            'content'  => [['type' => 'text', 'text' => is_string($code) ? $code : '', 'styles' => $this->emptyStyles()]],
            'children' => [],
        ];
    }

    private function convertDelimiter(): array
    {
        return [
            'id'       => $this->generateId(),
            'type'     => 'divider',
            'props'    => $this->defaultProps(),
            'content'  => [],
            'children' => [],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function convertChecklist(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return [];
        }

        $blocks = [];
        foreach ($items as $item) {
            $text = $item['text'] ?? '';
            $checked = !empty($item['checked']);
            $blocks[] = [
                'id'       => $this->generateId(),
                'type'     => 'checkListItem',
                'props'    => $this->defaultProps(['checked' => $checked]),
                'content'  => $this->htmlToInlineContent($text),
                'children' => [],
            ];
        }
        return $blocks;
    }

    private function convertTable(array $data): array
    {
        $editorRows = $data['content'] ?? [];
        $rows = [];
        foreach ($editorRows as $editorRow) {
            $cells = [];
            foreach ($editorRow as $cellText) {
                $cells[] = $this->htmlToInlineContent(is_string($cellText) ? $cellText : '');
            }
            $rows[] = ['cells' => $cells];
        }

        return [
            'id'       => $this->generateId(),
            'type'     => 'table',
            'props'    => $this->defaultProps(),
            'content'  => ['type' => 'tableContent', 'rows' => $rows],
            'children' => [],
        ];
    }

    private function convertCallout(array $data): array
    {
        return [
            'id'       => $this->generateId(),
            'type'     => 'callout',
            'props'    => $this->defaultProps([
                'backgroundColor' => $data['backgroundColor'] ?? '#FFF8E7',
                'borderColor'     => '#F59E0B',
                'textColor'       => $data['textColor'] ?? '#92400E',
                'icon'            => '💡',
                'label'           => '',
            ]),
            'content'  => $this->htmlToInlineContent($data['text'] ?? ''),
            'children' => [],
        ];
    }

    private function convertSponsorBlock(): array
    {
        return [
            'id'       => $this->generateId(),
            'type'     => 'sponsorBlock',
            'props'    => $this->defaultProps(['name' => '', 'logoUrl' => '', 'websiteUrl' => '']),
            'content'  => [],
            'children' => [],
        ];
    }

    private function convertScheduleBlock(): array
    {
        return [
            'id'       => $this->generateId(),
            'type'     => 'scheduleBlock',
            'props'    => $this->defaultProps(),
            'content'  => [],
            'children' => [],
        ];
    }

    private function convertColumns(array $data): array
    {
        $columnData = $data['cols'] ?? $data['columns'] ?? [];
        $columnBlocks = [];

        foreach ($columnData as $col) {
            $innerBlocks = $col['blocks'] ?? [];
            $convertedChildren = [];
            foreach ($innerBlocks as $innerBlock) {
                foreach ($this->convertBlock($innerBlock) as $bn) {
                    $convertedChildren[] = $bn;
                }
            }
            $columnBlocks[] = [
                'id'       => $this->generateId(),
                'type'     => 'columnBlock',
                'props'    => [],
                'content'  => [],
                'children' => $convertedChildren,
            ];
        }

        return [
            'id'       => $this->generateId(),
            'type'     => 'columns',
            'props'    => $this->defaultProps(['columnCount' => count($columnBlocks)]),
            'content'  => [],
            'children' => $columnBlocks,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function htmlToInlineContent(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return [['type' => 'text', 'text' => strip_tags($html), 'styles' => $this->emptyStyles()]];
        }

        return $this->domNodeToInlineContent($body, []);
    }

    /** @return array<int, array<string, mixed>> */
    private function domNodeToInlineContent(\DOMNode $node, array $activeStyles): array
    {
        $result = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $child->nodeValue;
                if ($text !== '' && $text !== null) {
                    $result[] = ['type' => 'text', 'text' => $text, 'styles' => $this->normalizeStyles($activeStyles)];
                }
            } elseif ($child instanceof \DOMElement) {
                $newStyles = $activeStyles;
                switch (strtolower($child->tagName)) {
                    case 'b':
                    case 'strong':
                        $newStyles['bold'] = true;
                        break;
                    case 'i':
                    case 'em':
                        $newStyles['italic'] = true;
                        break;
                    case 'u':
                        $newStyles['underline'] = true;
                        break;
                    case 's':
                    case 'del':
                        $newStyles['strikethrough'] = true;
                        break;
                    case 'code':
                        $newStyles['code'] = true;
                        break;
                    case 'mark':
                        $newStyles['backgroundColor'] = 'yellow';
                        break;
                    case 'br':
                        $result[] = ['type' => 'text', 'text' => "\n", 'styles' => $this->normalizeStyles($activeStyles)];
                        continue 2;
                    case 'a':
                        $href = $child->getAttribute('href');
                        $inner = $this->domNodeToInlineContent($child, $newStyles);
                        if ($href !== '' && $inner !== []) {
                            $result[] = ['type' => 'link', 'href' => $href, 'content' => $inner];
                        } else {
                            foreach ($inner as $item) {
                                $result[] = $item;
                            }
                        }
                        continue 2;
                }
                foreach ($this->domNodeToInlineContent($child, $newStyles) as $item) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    /** @return array<string, mixed> */
    private function normalizeStyles(array $styles): array
    {
        return [
            'bold'            => $styles['bold'] ?? false,
            'italic'          => $styles['italic'] ?? false,
            'underline'       => $styles['underline'] ?? false,
            'strikethrough'   => $styles['strikethrough'] ?? false,
            'code'            => $styles['code'] ?? false,
            'backgroundColor' => $styles['backgroundColor'] ?? 'default',
            'textColor'       => $styles['textColor'] ?? 'default',
        ];
    }

    /** @return array<string, mixed> */
    private function emptyStyles(): array
    {
        return $this->normalizeStyles([]);
    }

    /** @return array<string, mixed> */
    private function defaultProps(array $extra = []): array
    {
        return array_merge([
            'backgroundColor' => 'default',
            'textColor'       => 'default',
            'textAlignment'   => 'left',
        ], $extra);
    }

    private function generateId(): string
    {
        return substr(str_replace(['+', '/', '='], ['', '', ''], base64_encode(random_bytes(8))), 0, 10);
    }
}
