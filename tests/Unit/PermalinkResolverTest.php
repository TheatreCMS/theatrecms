<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Term;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\PermalinkResolver;
use TheatreCMS\Twig\PermalinkExtension;

class PermalinkResolverTest extends TestCase
{
    public function testResolvesSeasonUrlUnderDefaultPrefix(): void
    {
        $season = new Season('2026', '2026 Season');

        $resolver = new PermalinkResolver(new ContentTypeRegistry(), new TaxonomyRegistry());

        $this->assertSame('/seasons/2026', $resolver->resolve($season));
    }

    public function testResolvesSeasonUrlUnderRewrittenPrefix(): void
    {
        $season = new Season('2026', '2026 Season');

        $resolver = new PermalinkResolver(new ContentTypeRegistry(['seasons' => 'shows']), new TaxonomyRegistry());

        $this->assertSame('/shows/2026', $resolver->resolve($season));
    }

    public function testResolvesProductionUrlNestedUnderItsSeasonsPrefix(): void
    {
        $season = new Season('2026', '2026 Season');
        $production = new Production('Hamlet', $season);
        $production->setSlug('hamlet');

        $resolver = new PermalinkResolver(new ContentTypeRegistry(['seasons' => 'shows']), new TaxonomyRegistry());

        $this->assertSame('/shows/2026/hamlet', $resolver->resolve($production));
    }

    public function testResolvesPersonUrlUnderPeoplePrefix(): void
    {
        $person = new Person();
        $person->setFirstName('Jane');
        $person->setLastName('Doe');
        $person->setSlug('jane-doe');

        $resolver = new PermalinkResolver(new ContentTypeRegistry(), new TaxonomyRegistry());

        $this->assertSame('/people/jane-doe', $resolver->resolve($person));
    }

    public function testResolvesPageUrlAtSiteRootWithNoPrefix(): void
    {
        $page = new Page('About', \TheatreCMS\Enums\ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $resolver = new PermalinkResolver(new ContentTypeRegistry(['seasons' => 'shows']), new TaxonomyRegistry());

        $this->assertSame('/about', $resolver->resolve($page));
    }

    public function testArchiveUrlUsesRewrittenPrefix(): void
    {
        $resolver = new PermalinkResolver(new ContentTypeRegistry(['seasons' => 'shows']), new TaxonomyRegistry());

        $this->assertSame('/shows', $resolver->archiveUrl('seasons'));
    }

    public function testExtensionDelegatesToResolver(): void
    {
        $season = new Season('2026', '2026 Season');

        $extension = new PermalinkExtension(new PermalinkResolver(new ContentTypeRegistry(['seasons' => 'shows']), new TaxonomyRegistry()));

        $this->assertSame('/shows/2026', $extension->thePermalink($season));
        $this->assertSame('/shows', $extension->archiveUrl('seasons'));
    }

    public function testResolvesTermUrlUnderItsTaxonomysUrlPrefix(): void
    {
        $taxonomies = new TaxonomyRegistry();
        $taxonomies->register('post_category', ['post'], ['url_prefix' => 'category']);
        $taxonomies->register('genre', ['work']);

        $resolver = new PermalinkResolver(new ContentTypeRegistry(), $taxonomies);

        $this->assertSame('/category/news', $resolver->resolve(new Term('post_category', 'News', 'news')));
        $this->assertSame('/genre/comedy', $resolver->resolve(new Term('genre', 'Comedy', 'comedy')));
    }
}
