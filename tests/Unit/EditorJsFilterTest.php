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
}
