<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\ThemeManager;
use TheatreCMS\Twig\EditorJsExtension;
use Twig\Markup;

class EditorJsFilterTest extends TestCase
{
    private EditorJsHtmlConverter $converter;
    private string $fixtureThemesDir;

    protected function setUp(): void
    {
        parent::setUp();

        // A self-contained theme.json fixture, independent of the real
        // shipped default/avlt palettes, so these tests don't break if
        // those are edited later. "blue" is deliberately first so it
        // doubles as the "no colorScheme stored" default-fallback case.
        $this->fixtureThemesDir = sys_get_temp_dir() . '/theatrecms-test-themes-' . uniqid();
        mkdir($this->fixtureThemesDir . '/fixture', 0777, true);
        file_put_contents(
            $this->fixtureThemesDir . '/fixture/theme.json',
            json_encode([
                'settings' => [
                    'color' => [
                        'palette' => [
                            ['name' => 'blue', 'label' => 'Blue', 'color' => '#3b82f6'],
                            ['name' => 'purple', 'label' => 'Purple', 'color' => '#a855f7'],
                        ],
                    ],
                ],
            ])
        );

        $themeManager = new ThemeManager($this->fixtureThemesDir, 'fixture');
        $this->converter = new EditorJsHtmlConverter($themeManager);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixtureThemesDir . '/fixture/theme.json');
        @rmdir($this->fixtureThemesDir . '/fixture');
        @rmdir($this->fixtureThemesDir);
        parent::tearDown();
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

    public function testConverterDefaultsQuoteColorSchemeWhenMissing(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'quote',
                    'data' => [
                        'text' => 'The show must go on.',
                        'caption' => 'Freddie Mercury',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<blockquote class="kg-card kg-callout-card" style="background-color: #3b82f6;">', $html);
        $this->assertStringContainsString('<p>The show must go on.</p>', $html);
        $this->assertStringContainsString('<cite>Freddie Mercury</cite>', $html);
    }

    public function testConverterRendersQuoteWithValidColorScheme(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'quote',
                    'data' => [
                        'text' => 'All the world\'s a stage.',
                        'caption' => 'Shakespeare',
                        'colorScheme' => 'purple',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<blockquote class="kg-card kg-callout-card" style="background-color: #a855f7;">', $html);
    }

    public function testConverterRejectsUnknownQuoteColorScheme(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'quote',
                    'data' => [
                        'text' => 'Message',
                        'colorScheme' => 'javascript:alert(1)" onclick="evil()',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('background-color: #3b82f6;', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testConverterRendersCalloutWithIconAndLabel(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Rehearsals begin next Monday.',
                        'label' => 'Notice',
                        'icon' => '📣',
                        'colorScheme' => 'purple',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<div class="kg-card kg-callout-card" style="background-color: #a855f7;">', $html);
        $this->assertStringContainsString('<span class="kg-callout-icon">📣</span>', $html);
        $this->assertStringContainsString('<span class="kg-callout-label">Notice</span>', $html);
        $this->assertStringContainsString('<p>Rehearsals begin next Monday.</p>', $html);
    }

    public function testConverterDefaultsCalloutColorSchemeWhenMissing(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Message',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('background-color: #3b82f6;', $html);
    }

    public function testConverterRejectsUnknownCalloutColorScheme(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Message',
                        'colorScheme' => 'javascript:alert(1)" onclick="evil()',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('background-color: #3b82f6;', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testConverterPreservesSafeLegacyCalloutColors(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Legacy message',
                        'backgroundColor' => '#FFF8E7',
                        'borderColor' => '#F59E0B',
                        'textColor' => '#92400E',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('background-color: #FFF8E7;', $html);
        $this->assertStringContainsString('border: 1px solid #F59E0B;', $html);
        $this->assertStringContainsString('color: #92400E;', $html);
    }

    public function testConverterRejectsUnsafeLegacyCalloutColors(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Legacy message',
                        'backgroundColor' => 'red; } body { display: none',
                        'borderColor' => 'url(javascript:alert(1))',
                        'textColor' => 'expression(alert(1))',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('background-color: #3b82f6;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('expression(', $html);
        $this->assertStringNotContainsString('display: none', $html);
    }

    public function testConverterUsesLegacyColorsWhenStoredThemeSchemeNoLongerExists(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Legacy message',
                        'colorScheme' => 'removed-theme-color',
                        'backgroundColor' => '#FFF8E7',
                        'borderColor' => '#F59E0B',
                        'textColor' => '#92400E',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('background-color: #FFF8E7;', $html);
        $this->assertStringContainsString('border: 1px solid #F59E0B;', $html);
        $this->assertStringContainsString('color: #92400E;', $html);
    }

    public function testConverterOmitsCalloutHeaderWhenNoIconOrLabel(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'text' => 'Message',
                        'icon' => '',
                        'label' => '',
                    ],
                ],
            ],
        ]));

        $this->assertStringNotContainsString('kg-callout-header', $html);
    }

    public function testConverterDropsCalloutWithoutText(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'callout',
                    'data' => [
                        'label' => 'Notice',
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

    public function testConverterRendersCarouselBlock(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            ['url' => 'https://example.com/one.jpg', 'caption' => 'First slide'],
                            ['url' => 'https://example.com/two.jpg', 'caption' => 'Second slide'],
                        ],
                        'autoplay' => true,
                        'autoplaySpeed' => 5000,
                        'showArrows' => false,
                        'showDots' => true,
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<div class="editorjs-carousel" data-autoplay="true" data-autoplay-speed="5000" data-arrows="false" data-dots="true">', $html);
        $this->assertStringContainsString('<figure class="editorjs-carousel__slide"><img src="https://example.com/one.jpg" alt="First slide" loading="lazy" /><figcaption>First slide</figcaption></figure>', $html);
        $this->assertStringContainsString('<figure class="editorjs-carousel__slide"><img src="https://example.com/two.jpg" alt="Second slide" loading="lazy" /><figcaption>Second slide</figcaption></figure>', $html);
    }

    public function testConverterSkipsMalformedImageGalleryItems(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'imageGallery',
                    'data' => [
                        'items' => [
                            null,
                            ['url' => []],
                            ['url' => 'https://example.com/no-caption.jpg', 'caption' => []],
                            ['url' => 'https://example.com/valid.jpg', 'caption' => 'Valid'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('https://example.com/no-caption.jpg', $html);
        $this->assertStringContainsString('https://example.com/valid.jpg', $html);
        $this->assertStringContainsString('<figcaption>Valid</figcaption>', $html);
    }

    public function testConverterDropsCarouselWithNoSlides(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => ['items' => []],
                ],
            ],
        ]));

        $this->assertSame('', $html);
    }

    public function testConverterStripsUnsafeSlideUrl(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            ['url' => 'javascript:alert(1)', 'caption' => 'Evil'],
                            ['url' => 'https://example.com/valid.jpg', 'caption' => 'Valid'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('Evil', $html);
        $this->assertStringContainsString('https://example.com/valid.jpg', $html);
    }

    public function testConverterAcceptsRootRelativeUploadUrlsInCarousel(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            ['url' => '/uploads/6de202fb6d519fdb731be4ea.jpg', 'caption' => 'Local upload'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<img src="/uploads/6de202fb6d519fdb731be4ea.jpg"', $html);
    }

    public function testConverterRejectsProtocolRelativeUrlInCarousel(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            ['url' => '//evil.example.com/x.jpg', 'caption' => 'Off-origin'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertSame('', $html);
    }

    public function testConverterRejectsBackslashProtocolRelativeUrlInCarousel(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            ['url' => '/\\evil.example.com/x.jpg', 'caption' => 'Off-origin'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertSame('', $html);
    }

    public function testConverterSkipsMalformedCarouselItems(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            null,
                            'not-an-item',
                            ['url' => []],
                            ['url' => 'https://example.com/no-caption.jpg', 'caption' => []],
                            ['url' => 'https://example.com/valid.jpg', 'caption' => 'Valid'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('https://example.com/no-caption.jpg', $html);
        $this->assertStringContainsString('https://example.com/valid.jpg', $html);
        $this->assertStringNotContainsString('not-an-item', $html);
    }

    public function testConverterAcceptsRootRelativeUploadUrlInImageBlock(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'image',
                    'data' => [
                        'file' => ['url' => '/uploads/photo.jpg'],
                        'caption' => 'A photo',
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('<img src="/uploads/photo.jpg"', $html);
    }

    public function testConverterCarouselDefaultsWhenSettingsOmitted(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'carousel',
                    'data' => [
                        'items' => [
                            ['url' => 'https://example.com/one.jpg'],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertStringContainsString('data-autoplay="false"', $html);
        $this->assertStringContainsString('data-autoplay-speed="3000"', $html);
        $this->assertStringContainsString('data-arrows="true"', $html);
        $this->assertStringContainsString('data-dots="true"', $html);
    }

    public function testConverterRetainsCiteTagInParagraph(): void
    {
        $html = $this->converter->toHtml(json_encode([
            'blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => 'Now playing: <cite>A Brave New World</cite>.'],
                ],
            ],
        ]));

        $this->assertStringContainsString('<cite>A Brave New World</cite>', $html);
    }
}
