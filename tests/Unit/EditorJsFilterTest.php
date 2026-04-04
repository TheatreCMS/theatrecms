<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Twig\EditorJsExtension;
use Twig\Markup;

class EditorJsFilterTest extends TestCase
{
    private EditorJsHtmlConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new EditorJsHtmlConverter();
    }

    public function testConverterRendersMultipleBlockTypes(): void
    {
        $payload = json_encode([
            'blocks' => [
                [
                    'type' => 'header',
                    'data' => [
                        'text' => 'A Brave New World',
                        'level' => 2,
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'data' => [
                        'text' => '<strong>Director</strong>: Someone',
                    ],
                ],
                [
                    'type' => 'list',
                    'data' => [
                        'style' => 'unordered',
                        'items' => [
                            'Act I',
                            'Act II',
                        ],
                    ],
                ],
            ],
        ]);

        $html = $this->converter->toHtml($payload);

        $this->assertStringContainsString('<h2>A Brave New World</h2>', $html);
        $this->assertStringContainsString('<strong>Director</strong>: Someone', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Act I</li>', $html);
    }

    public function testConverterHandlesPlainStringFallback(): void
    {
        $html = $this->converter->toHtml('A single paragraph without JSON.');

        $this->assertSame('<p>A single paragraph without JSON.</p>', $html);
    }

    public function testExtensionReturnsMarkupWithSanitizedOutput(): void
    {
        $extension = new EditorJsExtension($this->converter);
        $result = $extension->convert(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => [
                        'text' => 'Safe content',
                    ],
                ],
            ],
        ]));

        $this->assertInstanceOf(Markup::class, $result);
        $this->assertStringContainsString('<p>Safe content</p>', (string)$result);
    }

    public function testConverterDecodesNbspToCharacter(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Hello&nbsp;World'],
                ],
            ],
        ]));

        // &nbsp; must NOT appear as literal text; it should be the UTF-8 NBSP character
        $this->assertStringNotContainsString('&amp;nbsp;', $html);
        $this->assertStringNotContainsString('&nbsp;', $html);
        $this->assertStringContainsString("Hello\u{00A0}World", $html);
    }

    public function testConverterDecodesOtherNamedEntities(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'dash&mdash;here'],
                ],
            ],
        ]));

        $this->assertStringNotContainsString('&amp;mdash;', $html);
        $this->assertStringContainsString('dash—here', $html);
    }


    public function testConverterPassesThroughAnchorWithValidHref(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Visit <a href="https://example.com">our site</a> for info.'],
                ],
            ],
        ]));

        $this->assertStringContainsString('<a href="https://example.com">our site</a>', $html);
    }

    public function testConverterPassesThroughAnchorWithQueryString(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'See <a href="https://example.com/page?a=1&amp;b=2">details</a>.'],
                ],
            ],
        ]));

        $this->assertStringContainsString('<a href="https://example.com/page?a=1&amp;b=2">details</a>', $html);
    }

    public function testConverterStripsAnchorWithInvalidScheme(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Bad <a href="javascript:alert(1)">link</a>.'],
                ],
            ],
        ]));

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('Bad', $html);
        $this->assertStringContainsString('link', $html);
    }

    public function testConverterStripsAnchorWithNoHref(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Bare <a>link</a>.'],
                ],
            ],
        ]));

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('link', $html);
    }

    public function testConverterStripsDisallowedAttributesFromAnchor(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => '<a href="https://example.com" onclick="evil()" class="foo">text</a>'],
                ],
            ],
        ]));

        $this->assertStringContainsString('<a href="https://example.com">text</a>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('class=', $html);
    }
}
