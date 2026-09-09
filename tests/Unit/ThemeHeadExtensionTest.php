<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Person;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\SeoMeta;
use TheatreCMS\Theme\StructuredDataBuilder;
use TheatreCMS\Twig\ThemeHeadExtension;

class ThemeHeadExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HookManager::setInstance(new HookManager());
    }

    private function makeExtension(): ThemeHeadExtension
    {
        $siteSettings = $this->createStub(SiteSettings::class);
        $siteSettings->method('get')->willReturn('https://example.com');

        return new ThemeHeadExtension(new StructuredDataBuilder($siteSettings, new EditorJsHtmlConverter()));
    }

    public function testOmitsSeoTagsWhenNoSeoContextIsPresent(): void
    {
        $extension = $this->makeExtension();

        $head = $extension->renderThemeHead([]);

        $this->assertSame('', $head);
    }

    public function testEmitsAllTagsForAFullyPopulatedSeoMeta(): void
    {
        $extension = $this->makeExtension();
        $seo = new SeoMeta(
            title: 'About — Theatre',
            description: 'A short description.',
            canonicalUrl: 'https://example.com/about',
            ogImage: 'https://example.com/share.jpg',
            ogType: 'website',
            twitterCard: 'summary_large_image',
        );

        $head = $extension->renderThemeHead(['seo' => $seo]);

        $this->assertStringContainsString('<meta name="description" content="A short description.">', $head);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/about">', $head);
        $this->assertStringContainsString('<meta property="og:title" content="About — Theatre">', $head);
        $this->assertStringContainsString('<meta property="og:description" content="A short description.">', $head);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $head);
        $this->assertStringContainsString('<meta property="og:url" content="https://example.com/about">', $head);
        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/share.jpg">', $head);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $head);
    }

    public function testOmitsOptionalTagsForASparseSeoMeta(): void
    {
        $extension = $this->makeExtension();
        $seo = new SeoMeta(
            title: 'Theatre',
            description: '',
            canonicalUrl: '',
            ogImage: '',
            ogType: 'website',
            twitterCard: 'summary',
        );

        $head = $extension->renderThemeHead(['seo' => $seo]);

        $this->assertStringNotContainsString('name="description"', $head);
        $this->assertStringNotContainsString('rel="canonical"', $head);
        $this->assertStringNotContainsString('property="og:description"', $head);
        $this->assertStringNotContainsString('property="og:url"', $head);
        $this->assertStringNotContainsString('property="og:image"', $head);
        $this->assertStringContainsString('property="og:title" content="Theatre"', $head);
        $this->assertStringContainsString('name="twitter:card" content="summary"', $head);
    }

    public function testDoesNotDuplicateSeoTagsWhenStructuredDataIsAlsoPresent(): void
    {
        $extension = $this->makeExtension();
        $seo = new SeoMeta('Theatre', '', '', '', 'website', 'summary');
        $person = (new Person())->setFirstName('Jane')->setLastName('Doe')->setSlug('jane-doe');

        $head = $extension->renderThemeHead(['seo' => $seo, 'person' => $person]);

        $this->assertSame(1, substr_count($head, 'og:title'));
    }

    public function testSeoTagsAppearBeforeStructuredData(): void
    {
        $extension = $this->makeExtension();
        $seo = new SeoMeta('Theatre', '', '', '', 'website', 'summary');

        $person = (new Person())->setFirstName('Jane')->setLastName('Doe')->setSlug('jane-doe');

        $head = $extension->renderThemeHead(['seo' => $seo, 'person' => $person]);

        $seoPosition = strpos($head, 'og:title');
        $jsonLdPosition = strpos($head, 'application/ld+json');

        $this->assertNotFalse($seoPosition);
        $this->assertNotFalse($jsonLdPosition);
        $this->assertLessThan($jsonLdPosition, $seoPosition);
    }
}
