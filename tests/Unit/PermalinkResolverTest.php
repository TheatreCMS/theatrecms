<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Entry;
use TheatreCMS\Models\Event;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Venue;
use TheatreCMS\Models\Term;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\ContentType\PermalinkResolver;
use TheatreCMS\Twig\PermalinkExtension;
use TheatreCMS\Tests\Includes\BuildsTaxonomyRegistries;

class PermalinkResolverTest extends TestCase
{
    use BuildsTaxonomyRegistries;

    public function testResolvesSeasonUrlUnderDefaultPrefix(): void
    {
        $season = new Season('2026', '2026 Season');

        $resolver = new PermalinkResolver($this->contentTypes(), self::taxonomyRegistry());

        $this->assertSame('/seasons/2026', $resolver->resolve($season));
    }

    public function testResolvesSeasonUrlUnderRewrittenPrefix(): void
    {
        $season = new Season('2026', '2026 Season');

        $resolver = $this->resolverWithSeasonsAtShows();

        $this->assertSame('/shows/2026', $resolver->resolve($season));
    }

    public function testResolvesProductionUrlNestedUnderItsSeasonsPrefix(): void
    {
        $season = new Season('2026', '2026 Season');
        $production = new Production('Hamlet', $season);
        $production->setSlug('hamlet');

        $resolver = $this->resolverWithSeasonsAtShows();

        $this->assertSame('/shows/2026/hamlet', $resolver->resolve($production));
    }

    public function testResolvesPersonUrlUnderPeoplePrefix(): void
    {
        $person = new Person();
        $person->setFirstName('Jane');
        $person->setLastName('Doe');
        $person->setSlug('jane-doe');

        $resolver = new PermalinkResolver($this->contentTypes(), self::taxonomyRegistry());

        $this->assertSame('/people/jane-doe', $resolver->resolve($person));
    }

    public function testResolvesEntryUrlUnderItsContentTypesPrefix(): void
    {
        $contentTypes = $this->contentTypes(['press_release' => ['urlPrefix' => 'news']]);
        $contentTypes->register('press_release', ['urlPrefix' => 'press-releases']);
        $entry = new Entry([
            'contentType' => 'press_release',
            'title' => 'Season Announced',
            'slug' => 'season-announced',
        ]);

        $resolver = new PermalinkResolver($contentTypes, self::taxonomyRegistry());

        $this->assertSame('/news/season-announced', $resolver->resolve($entry));
    }

    public function testResolvesEventUrlUnderEventsPrefix(): void
    {
        $event = new Event(new \DateTimeImmutable('2026-11-01 19:30'), 'scheduled', null, 'Opening Gala');
        $event->setSlug('2026-11-01-opening-gala');

        $resolver = new PermalinkResolver($this->contentTypes(), self::taxonomyRegistry());

        $this->assertSame('/events/2026-11-01-opening-gala', $resolver->resolve($event));
    }

    public function testResolvesPageUrlAtSiteRootWithNoPrefix(): void
    {
        $page = new Page('About', \TheatreCMS\Enums\ContentStatus::PUBLISHED, 'body');
        $page->setSlug('about');

        $resolver = $this->resolverWithSeasonsAtShows();

        $this->assertSame('/about', $resolver->resolve($page));
    }

    public function testResolvesNestedPageUrlsFromTheirAncestors(): void
    {
        $about = $this->page('about');
        $board = $this->page('board-of-directors')->setParent($about);
        $chair = $this->page('chair')->setParent($board);

        $resolver = $this->resolverWithSeasonsAtShows();

        $this->assertSame('/about/board-of-directors', $resolver->resolve($board));
        $this->assertSame('/about/board-of-directors/chair', $resolver->resolve($chair));
    }

    public function testResolvesNestedEntryUrlsUnderTheirTypesPrefix(): void
    {
        $contentTypes = $this->contentTypes();
        $contentTypes->register('program', ['urlPrefix' => 'programs', 'hierarchical' => true]);
        $parent = new Entry(['contentType' => 'program', 'title' => 'Education', 'slug' => 'education']);
        $child = (new Entry(['contentType' => 'program', 'title' => 'Camps', 'slug' => 'camps']))->setParent($parent);

        $resolver = new PermalinkResolver($contentTypes, self::taxonomyRegistry());

        $this->assertSame('/programs/education/camps', $resolver->resolve($child));
    }

    public function testResolvesVenueUrlUnderVenuesPrefix(): void
    {
        $venue = (new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000'))->setSlug('main-stage');

        $resolver = new PermalinkResolver($this->contentTypes(), self::taxonomyRegistry());

        $this->assertSame('/venues/main-stage', $resolver->resolve($venue));
    }

    public function testATypeThatIsNotPublicHasNoPermalinks(): void
    {
        $sponsor = (new Sponsor())->setSlug('acme');

        $resolver = new PermalinkResolver($this->contentTypes(), self::taxonomyRegistry());

        $this->expectException(\InvalidArgumentException::class);

        $resolver->resolve($sponsor);
    }

    public function testArchiveUrlUsesRewrittenPrefix(): void
    {
        $resolver = $this->resolverWithSeasonsAtShows();

        $this->assertSame('/shows', $resolver->archiveUrl('season'));
    }

    public function testExtensionDelegatesToResolver(): void
    {
        $season = new Season('2026', '2026 Season');

        $extension = new PermalinkExtension($this->resolverWithSeasonsAtShows());

        $this->assertSame('/shows/2026', $extension->thePermalink($season));
        $this->assertSame('/shows', $extension->archiveUrl('season'));
    }

    public function testResolvesTermUrlUnderItsTaxonomysUrlPrefix(): void
    {
        $taxonomies = self::taxonomyRegistry();
        $taxonomies->register('post_category', ['post'], ['urlPrefix' => 'category']);
        $taxonomies->register('genre', ['work']);

        $resolver = new PermalinkResolver($this->contentTypes(), $taxonomies);

        $this->assertSame('/category/news', $resolver->resolve(new Term('post_category', 'News', 'news')));
        $this->assertSame('/genre/comedy', $resolver->resolve(new Term('genre', 'Comedy', 'comedy')));
    }

    private function page(string $slug): Page
    {
        return (new Page(ucfirst($slug), \TheatreCMS\Enums\ContentStatus::PUBLISHED, 'body'))->setSlug($slug);
    }

    /**
     * A resolver whose `season` content type is overridden to serve under `/shows`.
     */
    private function resolverWithSeasonsAtShows(): PermalinkResolver
    {
        return new PermalinkResolver(
            $this->contentTypes(['season' => ['urlPrefix' => 'shows']]),
            self::taxonomyRegistry()
        );
    }

    /**
     * A registry with the content types these tests link to.
     *
     * @param array<string, array<string, mixed>> $overrides
     */
    private function contentTypes(array $overrides = []): ContentTypeRegistry
    {
        $registry = new ContentTypeRegistry($overrides);

        $prefixes = [
            'season' => 'seasons',
            'production' => 'productions',
            'person' => 'people',
            'work' => 'works',
            'post' => 'posts',
            'event' => 'events',
            'venue' => 'venues',
        ];
        foreach ($prefixes as $type => $urlPrefix) {
            $registry->register($type, ['urlPrefix' => $urlPrefix]);
        }
        $registry->register('page', ['urlPrefix' => '', 'hasArchive' => false, 'hierarchical' => true]);
        $registry->register('sponsor', ['urlPrefix' => 'sponsors', 'public' => false]);

        return $registry;
    }
}
