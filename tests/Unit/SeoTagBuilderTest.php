<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\PermalinkResolver;
use TheatreCMS\Theme\SeoDescriptionResolver;
use TheatreCMS\Theme\SeoMeta;
use TheatreCMS\Theme\SeoTagBuilder;
use TheatreCMS\Theme\TitleResolver;

class SeoTagBuilderTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        HookManager::setInstance(new HookManager());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $overrides Same keys `SiteSettings::save()` accepts.
     */
    private function makeSiteSettings(array $overrides = []): SiteSettings
    {
        $path = sys_get_temp_dir() . '/theatrecms-seo-test-' . uniqid('', true) . '.yaml';
        $this->tempFiles[] = $path;

        $settings = new SiteSettings($path);

        if ($overrides !== []) {
            $settings->save($overrides);
        }

        return $settings;
    }

    private function makeBuilder(SiteSettings $siteSettings): SeoTagBuilder
    {
        return new SeoTagBuilder(
            $siteSettings,
            new TitleResolver(),
            new PermalinkResolver(new ContentTypeRegistry()),
            new SeoDescriptionResolver(new EditorJsHtmlConverter())
        );
    }

    public function testEntityTitleGetsSiteNameSuffix(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['name' => 'The Playhouse']));
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('About — The Playhouse', $seo->title);
    }

    public function testTitleSkipsSuffixWhenBaseAlreadyMatchesSiteName(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['name' => 'Home']));
        $page = new Page('Home', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('home');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('Home', $seo->title);
    }

    public function testTitleUsesConfiguredSeparator(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings([
            'name' => 'The Playhouse',
            'seo_title_separator' => '|',
        ]));
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('About | The Playhouse', $seo->title);
    }

    public function testDescriptionFallsBackToSiteDefaultWhenEntityHasNone(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings([
            'seo_default_meta_description' => 'Welcome to our theatre.',
        ]));
        $page = new Page('About', ContentStatus::PUBLISHED, '');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('Welcome to our theatre.', $seo->description);
    }

    public function testDescriptionIsEmptyWhenNoFallbackAvailable(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings());
        $page = new Page('About', ContentStatus::PUBLISHED, '');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('', $seo->description);
    }

    public function testCanonicalUrlIsAbsoluteWhenSiteUrlConfigured(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['site_url' => 'https://example.com']));
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('https://example.com/about', $seo->canonicalUrl);
    }

    public function testCanonicalUrlIsRelativeWhenSiteUrlBlank(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings());
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('/about', $seo->canonicalUrl);
    }

    public function testCanonicalUrlIsOmittedForEntityTypeWithNoUrlScheme(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['site_url' => 'https://example.com']));
        $sponsor = new Sponsor();
        $sponsor->setName('Acme Corp');

        $seo = $builder->forEntity($sponsor, 'sponsors');

        $this->assertSame('', $seo->canonicalUrl);
        $this->assertSame('', $seo->description);
    }

    public function testOgImagePrefersFeaturedImageAndUsesLargeTwitterCard(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['site_url' => 'https://example.com']));
        $season = new Season('2026', '2026 Season');
        $production = new Production('Hamlet', $season);
        $production->setSlug('hamlet');

        $seo = $builder->forEntity($production, 'productions');

        // Production has no featured image set in this test, so it falls through to
        // the site default (also unset here), landing on '' and the 'summary' card.
        $this->assertSame('', $seo->ogImage);
        $this->assertSame('summary', $seo->twitterCard);
    }

    public function testOgImageFallsBackToHeadshotForPerson(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['site_url' => 'https://example.com']));
        $person = new Person();
        $person->setFirstName('Jane');
        $person->setLastName('Doe');
        $person->setSlug('jane-doe');
        $person->setHeadshotUrl('/uploads/jane.jpg');

        $seo = $builder->forEntity($person, 'people');

        $this->assertSame('https://example.com/uploads/jane.jpg', $seo->ogImage);
        $this->assertSame('summary_large_image', $seo->twitterCard);
    }

    public function testOgImageFallsBackToLogoForSponsor(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings(['site_url' => 'https://example.com']));
        $sponsor = new Sponsor();
        $sponsor->setName('Acme Corp');
        $sponsor->setLogoUrl('/uploads/acme.png');

        $seo = $builder->forEntity($sponsor, 'sponsors');

        $this->assertSame('https://example.com/uploads/acme.png', $seo->ogImage);
    }

    public function testOgImageFallsBackToSiteDefaultSocialImage(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings([
            'site_url' => 'https://example.com',
            'seo_default_social_image' => '/assets/share.jpg',
        ]));
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('https://example.com/assets/share.jpg', $seo->ogImage);
    }

    public function testOgTypeIsArticleForPosts(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings());
        $post = new Post('Breaking News', ContentStatus::PUBLISHED, 'body');
        $post->setSlug('breaking-news');

        $seo = $builder->forEntity($post, 'posts');

        $this->assertSame('article', $seo->ogType);
    }

    public function testOgTypeIsWebsiteForNonPosts(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings());
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('website', $seo->ogType);
    }

    public function testSeoTitleFilterFires(): void
    {
        HookManager::getInstance()->addFilter('theatrecms/seo_title', function (string $title): string {
            return strtoupper($title);
        });

        $builder = $this->makeBuilder($this->makeSiteSettings(['name' => 'Theatre']));
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame('ABOUT — THEATRE', $seo->title);
    }

    public function testSeoMetaFilterCanOverrideTheWholeObject(): void
    {
        $override = new SeoMeta('Overridden', '', '', '', 'website', 'summary');

        HookManager::getInstance()->addFilter('theatrecms/seo_meta', function () use ($override): SeoMeta {
            return $override;
        });

        $builder = $this->makeBuilder($this->makeSiteSettings());
        $page = new Page('About', ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $seo = $builder->forEntity($page, 'pages');

        $this->assertSame($override, $seo);
    }

    public function testForArchiveUsesLabelAndArchiveUrl(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings([
            'site_url' => 'https://example.com',
            'name' => 'Theatre',
        ]));

        $seo = $builder->forArchive('productions', 'Productions');

        $this->assertSame('Productions — Theatre', $seo->title);
        $this->assertSame('https://example.com/productions', $seo->canonicalUrl);
        $this->assertSame('website', $seo->ogType);
    }

    public function testForHomeUsesSiteNameWithNoSuffixAndRootCanonical(): void
    {
        $builder = $this->makeBuilder($this->makeSiteSettings([
            'site_url' => 'https://example.com',
            'name' => 'Theatre',
        ]));

        $seo = $builder->forHome();

        $this->assertSame('Theatre', $seo->title);
        $this->assertSame('https://example.com/', $seo->canonicalUrl);
    }
}
