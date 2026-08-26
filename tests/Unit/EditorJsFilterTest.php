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

    public function testConverterRendersBookmarkCardWithFullMetadata(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'linkTool',
                    'data' => [
                        'link' => 'https://www.avlt.info/dirty-laundry/',
                        'meta' => [
                            'title' => 'Dirty Laundry',
                            'description' => 'Written by Mathilde Dratwa, directed by Drew Eberly.',
                            'image' => ['url' => 'https://storage.ghost.io/dirty-laundry-thumb.jpg'],
                            'publisher' => 'Available Light Theatre',
                            'icon' => 'https://storage.ghost.io/avlt-icon.jpg',
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<figure class="kg-card kg-bookmark-card">', $html);
        $this->assertStringContainsString('<a class="kg-bookmark-container" href="https://www.avlt.info/dirty-laundry/">', $html);
        $this->assertStringContainsString('<div class="kg-bookmark-title">Dirty Laundry</div>', $html);
        $this->assertStringContainsString('<div class="kg-bookmark-description">Written by Mathilde Dratwa, directed by Drew Eberly.</div>', $html);
        $this->assertStringContainsString('<span class="kg-bookmark-author">Available Light Theatre</span>', $html);
        $this->assertStringContainsString('<img class="kg-bookmark-icon" src="https://storage.ghost.io/avlt-icon.jpg" alt="">', $html);
        $this->assertStringContainsString('<div class="kg-bookmark-thumbnail"><img src="https://storage.ghost.io/dirty-laundry-thumb.jpg" alt="" loading="lazy"></div>', $html);
    }

    public function testConverterRendersBookmarkCardWithMinimalMetadata(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'linkTool',
                    'data' => [
                        'link' => 'https://example.com/some-page',
                        'meta' => [],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<div class="kg-bookmark-title">example.com</div>', $html);
        $this->assertStringNotContainsString('kg-bookmark-description', $html);
        $this->assertStringNotContainsString('kg-bookmark-metadata', $html);
        $this->assertStringNotContainsString('kg-bookmark-thumbnail', $html);
    }

    public function testConverterDropsBookmarkCardWithUnsafeLink(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'linkTool',
                    'data' => [
                        'link' => 'javascript:alert(1)',
                        'meta' => ['title' => 'Evil'],
                    ],
                ],
            ],
        ]));

        $this->assertSame('', $html);
    }

    public function testConverterRendersCtaCard(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'ctaCard',
                    'data' => [
                        'text' => 'Reserve your seats now through the CAPA ticketing portal.',
                        'buttonText' => 'Go to CAPA Ticketing →',
                        'buttonUrl' => 'https://tickets.capa.com/overview/10611?ref=avlt.info',
                        'backgroundColor' => 'purple',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<div class="kg-card kg-cta-card kg-cta-bg-purple kg-cta-immersive kg-cta-centered">', $html);
        $this->assertStringContainsString('<div class="kg-cta-text"><p>Reserve your seats now through the CAPA ticketing portal.</p></div>', $html);
        $this->assertStringContainsString('<a href="https://tickets.capa.com/overview/10611?ref=avlt.info" class="kg-cta-button kg-style-accent"', $html);
        $this->assertStringContainsString('Go to CAPA Ticketing', $html);
    }

    public function testConverterRejectsUnknownCtaCardBackgroundPreset(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'ctaCard',
                    'data' => [
                        'text' => 'Message',
                        'buttonText' => 'Go',
                        'buttonUrl' => 'https://example.com',
                        'backgroundColor' => 'javascript:alert(1)" onclick="evil()',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('kg-cta-bg-purple', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testConverterDefaultsCtaCardButtonTextWhenMissing(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'ctaCard',
                    'data' => [
                        'text' => 'Message',
                        'buttonUrl' => 'https://example.com',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('>Learn more</a>', $html);
    }

    public function testConverterDropsCtaCardWithoutButtonUrl(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'ctaCard',
                    'data' => [
                        'text' => 'Message with no link',
                    ],
                ],
            ],
        ]));

        $this->assertSame('', $html);
    }

    public function testConverterDropsCtaCardWithoutText(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'ctaCard',
                    'data' => [
                        'buttonUrl' => 'https://example.com',
                    ],
                ],
            ],
        ]));

        $this->assertSame('', $html);
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
