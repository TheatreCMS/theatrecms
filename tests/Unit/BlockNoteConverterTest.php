<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Text\BlockNoteHtmlConverter;
use TheatreCMS\Twig\BlockNoteExtension;
use Twig\Markup;

class BlockNoteConverterTest extends TestCase
{
    private BlockNoteHtmlConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new BlockNoteHtmlConverter();
    }

    private function block(string $type, array $props = [], array $content = [], array $children = []): array
    {
        return ['id' => 'test', 'type' => $type, 'props' => $props, 'content' => $content, 'children' => $children];
    }

    private function text(string $text, array $styles = []): array
    {
        return ['type' => 'text', 'text' => $text, 'styles' => $styles];
    }

    public function testConverterRendersMultipleBlockTypes(): void
    {
        $payload = json_encode([
            $this->block('heading', ['level' => 2], [$this->text('A Brave New World')]),
            $this->block('paragraph', [], [$this->text('Bold', ['bold' => true]), $this->text(': text')]),
            $this->block('bulletListItem', [], [$this->text('Act I')]),
            $this->block('bulletListItem', [], [$this->text('Act II')]),
        ]);

        $html = $this->converter->toHtml($payload);

        $this->assertStringContainsString('<h2>A Brave New World</h2>', $html);
        $this->assertStringContainsString('<strong>Bold</strong>: text', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Act I</li>', $html);
        $this->assertStringContainsString('<li>Act II</li>', $html);
    }

    public function testConverterHandlesEmptyPayloads(): void
    {
        $this->assertSame('', $this->converter->toHtml(null));
        $this->assertSame('', $this->converter->toHtml(''));
        $this->assertSame('', $this->converter->toHtml('[]'));
        $this->assertSame('', $this->converter->toHtml([]));
    }

    public function testConverterGroupsBulletListItems(): void
    {
        $payload = json_encode([
            $this->block('bulletListItem', [], [$this->text('One')]),
            $this->block('bulletListItem', [], [$this->text('Two')]),
            $this->block('bulletListItem', [], [$this->text('Three')]),
        ]);

        $html = $this->converter->toHtml($payload);

        $this->assertSame(1, substr_count($html, '<ul>'));
        $this->assertSame(1, substr_count($html, '</ul>'));
        $this->assertStringContainsString('<li>One</li>', $html);
        $this->assertStringContainsString('<li>Three</li>', $html);
    }

    public function testConverterGroupsNumberedListItems(): void
    {
        $payload = json_encode([
            $this->block('numberedListItem', [], [$this->text('First')]),
            $this->block('numberedListItem', [], [$this->text('Second')]),
        ]);

        $html = $this->converter->toHtml($payload);

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>First</li>', $html);
        $this->assertStringContainsString('<li>Second</li>', $html);
    }

    public function testConverterRendersCheckListItems(): void
    {
        $payload = json_encode([
            $this->block('checkListItem', ['checked' => true], [$this->text('Done')]),
            $this->block('checkListItem', ['checked' => false], [$this->text('Pending')]),
        ]);

        $html = $this->converter->toHtml($payload);

        $this->assertStringContainsString('<ul class="bn-checklist">', $html);
        $this->assertStringContainsString('data-checked="true"', $html);
        $this->assertStringContainsString('bn-checklist-item--checked', $html);
        $this->assertStringContainsString('data-checked="false"', $html);
    }

    public function testConverterRendersInlineBold(): void
    {
        $payload = json_encode([
            $this->block('paragraph', [], [$this->text('Hello', ['bold' => true])]),
        ]);

        $this->assertStringContainsString('<strong>Hello</strong>', $this->converter->toHtml($payload));
    }

    public function testConverterRendersInlineItalicAndUnderline(): void
    {
        $payload = json_encode([
            $this->block('paragraph', [], [
                $this->text('A', ['italic' => true]),
                $this->text('B', ['underline' => true]),
            ]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('<em>A</em>', $html);
        $this->assertStringContainsString('<u>B</u>', $html);
    }

    public function testConverterRendersLinkWithValidHref(): void
    {
        $link = ['type' => 'link', 'href' => 'https://example.com', 'content' => [$this->text('our site')]];
        $payload = json_encode([
            $this->block('paragraph', [], [$link]),
        ]);

        $this->assertStringContainsString('<a href="https://example.com">our site</a>', $this->converter->toHtml($payload));
    }

    public function testConverterStripsLinkWithInvalidScheme(): void
    {
        $link = ['type' => 'link', 'href' => 'javascript:alert(1)', 'content' => [$this->text('bad')]];
        $payload = json_encode([
            $this->block('paragraph', [], [$link]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('bad', $html);
    }

    public function testConverterRendersCodeBlock(): void
    {
        $block = $this->block('codeBlock', ['language' => 'php'], [['type' => 'text', 'text' => 'echo "hello";', 'styles' => []]]);
        $payload = json_encode([$block]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('<pre><code>', $html);
        $this->assertStringContainsString('echo &quot;hello&quot;;', $html);
    }

    public function testConverterRendersImageWithValidUrl(): void
    {
        $payload = json_encode([
            $this->block('image', ['url' => 'https://example.com/img.jpg', 'caption' => 'A photo']),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('<figure class="bn-image">', $html);
        $this->assertStringContainsString('src="https://example.com/img.jpg"', $html);
        $this->assertStringContainsString('<figcaption>A photo</figcaption>', $html);
    }

    public function testConverterReturnsEmptyForImageWithInvalidUrl(): void
    {
        $payload = json_encode([
            $this->block('image', ['url' => 'javascript:alert(1)', 'caption' => '']),
        ]);

        $this->assertSame('', $this->converter->toHtml($payload));
    }

    public function testConverterRendersCalloutBlock(): void
    {
        $payload = json_encode([
            $this->block('callout', [
                'backgroundColor' => '#FFF8E7',
                'borderColor' => '#F59E0B',
                'textColor' => '#92400E',
                'icon' => '💡',
                'label' => 'Note',
            ], [$this->text('Important info')]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('class="bn-callout"', $html);
        $this->assertStringContainsString('background:#FFF8E7', $html);
        $this->assertStringContainsString('border-left:4px solid #F59E0B', $html);
        $this->assertStringContainsString('Important info', $html);
    }

    public function testConverterRendersImageGallery(): void
    {
        $items = json_encode([
            ['url' => 'https://example.com/a.jpg', 'caption' => 'First'],
            ['url' => 'https://example.com/b.jpg', 'caption' => ''],
        ]);
        $payload = json_encode([
            $this->block('imageGallery', ['layout' => 'grid', 'caption' => '', 'items' => $items]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('class="bn-gallery"', $html);
        $this->assertStringContainsString('data-layout="grid"', $html);
        $this->assertStringContainsString('https://example.com/a.jpg', $html);
        $this->assertStringContainsString('<figcaption>First</figcaption>', $html);
    }

    public function testExtensionReturnsMarkupInstance(): void
    {
        $extension = new BlockNoteExtension($this->converter);
        $result = $extension->convert(json_encode([
            $this->block('paragraph', [], [$this->text('Safe content')]),
        ]));

        $this->assertInstanceOf(Markup::class, $result);
        $this->assertStringContainsString('<p>Safe content</p>', (string)$result);
    }

    public function testConverterHandlesLegacyEditorJsJsonGracefully(): void
    {
        $editorJsPayload = json_encode([
            'time' => 1630000000000,
            'blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Old content']]],
            'version' => '2.31.5',
        ]);

        $this->assertSame('', $this->converter->toHtml($editorJsPayload));
    }

    public function testConverterRendersHeadingLevels(): void
    {
        $payload = json_encode([
            $this->block('heading', ['level' => 1], [$this->text('Big')]),
            $this->block('heading', ['level' => 3], [$this->text('Small')]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('<h1>Big</h1>', $html);
        $this->assertStringContainsString('<h3>Small</h3>', $html);
    }

    public function testConverterRendersQuote(): void
    {
        $payload = json_encode([
            $this->block('quote', [], [$this->text('To be or not to be')]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringContainsString('<blockquote class="bn-quote">', $html);
        $this->assertStringContainsString('To be or not to be', $html);
    }

    public function testConverterRendersDivider(): void
    {
        $payload = json_encode([
            $this->block('divider'),
        ]);

        $this->assertStringContainsString('<hr />', $this->converter->toHtml($payload));
    }

    public function testConverterEscapesXssInTextContent(): void
    {
        $payload = json_encode([
            $this->block('paragraph', [], [$this->text('<script>alert(1)</script>')]),
        ]);

        $html = $this->converter->toHtml($payload);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
