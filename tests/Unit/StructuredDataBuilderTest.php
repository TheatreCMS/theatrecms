<?php

namespace TheatreCMS\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Event as Performance;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Sponsorship;
use TheatreCMS\Models\Venue;
use TheatreCMS\Models\Work;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\StructuredDataBuilder;
use TheatreCMS\Theme\ThemeManager;

class StructuredDataBuilderTest extends TestCase
{
    private function makeBuilder(): StructuredDataBuilder
    {
        $siteSettings = $this->createStub(SiteSettings::class);
        $siteSettings->method('get')->willReturn('https://example.com');

        $themeManager = new ThemeManager(SRC_DIR . '/www/themes', 'default');

        return new StructuredDataBuilder($siteSettings, new EditorJsHtmlConverter($themeManager));
    }

    private function makePerson(string $first, string $last, string $slug): Person
    {
        $person = new Person();
        $person->setFirstName($first)->setLastName($last)->setSlug($slug);

        return $person;
    }

    public function testForPerson(): void
    {
        $person = $this->makePerson('Jane', 'Doe', 'jane-doe');
        $person->setBiography('An accomplished actor.');
        $person->setHeadshotUrl('https://example.com/jane.jpg');

        $schema = $this->makeBuilder()->forPerson($person);

        $this->assertEquals('Person', $schema['@type']);
        $this->assertEquals('Jane Doe', $schema['name']);
        $this->assertEquals('An accomplished actor.', $schema['description']);
        $this->assertEquals('https://example.com/jane.jpg', $schema['image']);
        $this->assertEquals('https://example.com/people/jane-doe', $schema['url']);
    }

    public function testForWork(): void
    {
        $author = $this->makePerson('William', 'Shakespeare', 'william-shakespeare');

        $work = new Work();
        $work->setTitle('Hamlet');
        $work->setSynopsis('A prince seeks revenge for his father\'s murder.');
        $work->setSlug('hamlet');
        $work->addCreator($author, 'Author');

        $schema = $this->makeBuilder()->forWork($work);

        $this->assertEquals('CreativeWork', $schema['@type']);
        $this->assertEquals('Hamlet', $schema['name']);
        $this->assertEquals('A prince seeks revenge for his father\'s murder.', $schema['description']);
        $this->assertEquals('https://example.com/works/hamlet', $schema['url']);
        $this->assertCount(1, $schema['author']);
        $this->assertEquals('William Shakespeare', $schema['author'][0]['name']);
    }

    private function makeProduction(): Production
    {
        $season = new Season('2026', '2026 Season');
        $work = new Work();
        $work->setTitle('Hamlet');
        $work->setSlug('hamlet');

        $production = new Production('Hamlet', $season, $work);
        $production->setSlug('hamlet');
        $production->setExcerpt('The Prince of Denmark returns.');
        $production->setDescription(json_encode([
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => 'The Prince of Denmark returns.']],
                ['type' => 'paragraph', 'data' => ['text' => 'A tale of <b>revenge</b> and madness.']],
            ],
        ]));
        $production->setOpening(new \DateTime('2026-06-01'));
        $production->setClosing(new \DateTime('2026-06-30'));
        $production->setTicketPurchaseUrl('https://example.com/tickets/hamlet');

        $venue = new Venue('The Grand Theatre', '123 Main St', 'Portland', 'Oregon', '97201');
        $production->setVenue($venue);

        $performer = $this->makePerson('Jane', 'Doe', 'jane-doe');
        $production->addPerformer($performer, 'Hamlet');

        $director = $this->makePerson('Sam', 'Director', 'sam-director');
        $production->addToProductionTeam($director, 'Director');

        return $production;
    }

    public function testForProductionWithoutPerformances(): void
    {
        $production = $this->makeProduction();

        $schema = $this->makeBuilder()->forProduction($production);

        $this->assertEquals('TheaterEvent', $schema['@type']);
        $this->assertEquals('Hamlet', $schema['name']);
        $this->assertEquals('https://example.com/seasons/2026/hamlet', $schema['url']);
        $this->assertEquals(
            "The Prince of Denmark returns.\n\nA tale of revenge and madness.",
            $schema['description']
        );
        $this->assertCount(1, $schema['workPerformed']);
        $this->assertEquals('Hamlet', $schema['workPerformed'][0]['name']);
        $this->assertEquals('Jane Doe', $schema['performers'][0]['name']);
        $this->assertEquals('Sam Director', $schema['directors'][0]['name']);
        $this->assertArrayHasKey('startDate', $schema);
        $this->assertArrayHasKey('endDate', $schema);
        $this->assertArrayHasKey('offers', $schema);
        $this->assertEquals('https://example.com/tickets/hamlet', $schema['offers'][0]['url']);
        $this->assertArrayNotHasKey('subEvents', $schema);
    }

    public function testForProductionWithPerformancesNestsSubEvents(): void
    {
        $production = $this->makeProduction();

        $performance = new Performance(
            new DateTimeImmutable('2026-06-05T19:00:00'),
            'scheduled',
            $production
        );
        $performance->setTicketUrl('https://example.com/tickets/hamlet/2026-06-05');

        $cancelled = new Performance(
            new DateTimeImmutable('2026-06-06T19:00:00'),
            'cancelled',
            $production
        );

        $schema = $this->makeBuilder()->forProduction($production, [$performance, $cancelled]);

        $this->assertArrayNotHasKey('startDate', $schema);
        $this->assertArrayNotHasKey('offers', $schema);
        $this->assertCount(2, $schema['subEvents']);
        $this->assertEquals('TheaterEvent', $schema['subEvents'][0]['@type']);
        $this->assertEquals('EventScheduled', $schema['subEvents'][0]['eventStatus']);
        $this->assertEquals(
            'https://example.com/tickets/hamlet/2026-06-05',
            $schema['subEvents'][0]['offers'][0]['url']
        );
        $this->assertEquals('Hamlet', $schema['subEvents'][0]['workPerformed'][0]['name']);
        $this->assertEquals('EventCancelled', $schema['subEvents'][1]['eventStatus']);
        $this->assertEquals('The Grand Theatre', $schema['subEvents'][0]['location']['name']);
        $this->assertEquals('The Grand Theatre', $schema['subEvents'][1]['location']['name']);
    }

    public function testForProductionSubEventUsesPerformancesOwnVenueWhenSet(): void
    {
        $production = $this->makeProduction();

        $touringVenue = new Venue('Riverside Hall', '9 River Rd', 'Salem', 'Oregon', '97301');

        $performance = new Performance(
            new DateTimeImmutable('2026-06-05T19:00:00'),
            'scheduled',
            $production
        );
        $performance->setVenue($touringVenue);

        $schema = $this->makeBuilder()->forProduction($production, [$performance]);

        $this->assertEquals('Riverside Hall', $schema['subEvents'][0]['location']['name']);
    }

    public function testForProductionWithMultipleWorksPerformed(): void
    {
        // e.g. a one-act festival evening combining several short plays
        $season = new Season('2026', '2026 Season');

        $trifles = new Work();
        $trifles->setTitle('Trifles')->setSlug('trifles');

        $stronger = new Work();
        $stronger->setTitle('The Stronger')->setSlug('the-stronger');

        $production = new Production('One-Act Festival', $season, [$trifles, $stronger]);
        $production->setSlug('one-act-festival');

        $schema = $this->makeBuilder()->forProduction($production);

        $this->assertCount(2, $schema['workPerformed']);
        $this->assertEquals('Trifles', $schema['workPerformed'][0]['name']);
        $this->assertEquals('The Stronger', $schema['workPerformed'][1]['name']);
    }

    public function testForSeasonNestsProductionsAsSubEvents(): void
    {
        $production = $this->makeProduction();
        $season = $production->getSeason();
        $season->setOverview('A season of classic tragedies.');
        $season->addProduction($production);

        $schema = $this->makeBuilder()->forSeason($season);

        $this->assertEquals('EventSeries', $schema['@type']);
        $this->assertEquals('2026 Season', $schema['name']);
        $this->assertEquals('A season of classic tragedies.', $schema['description']);
        $this->assertEquals('https://example.com/seasons/2026', $schema['url']);
        $this->assertCount(1, $schema['subEvents']);
        $this->assertEquals('TheaterEvent', $schema['subEvents'][0]['@type']);
        $this->assertEquals('Hamlet', $schema['subEvents'][0]['name']);
    }

    public function testForSeasonIncludesSponsors(): void
    {
        $season = new Season('2026', '2026 Season');

        $sponsor = new Sponsor();
        $sponsor->setName('Acme Bank');
        $sponsor->setWebsiteUrl('https://acme.example/sponsorships');
        $sponsor->setLogoUrl('https://acme.example/logo.png');

        $sponsorship = new Sponsorship($sponsor);
        $sponsorship->setSeason($season);
        $season->addSponsorship($sponsorship);

        $schema = $this->makeBuilder()->forSeason($season);

        $this->assertArrayHasKey('sponsors', $schema);
        $this->assertCount(1, $schema['sponsors']);
        $this->assertEquals('Organization', $schema['sponsors'][0]['@type']);
        $this->assertEquals('Acme Bank', $schema['sponsors'][0]['name']);
        $this->assertEquals('https://acme.example/sponsorships', $schema['sponsors'][0]['url']);
        $this->assertEquals('https://acme.example/logo.png', $schema['sponsors'][0]['image']);
    }
}
