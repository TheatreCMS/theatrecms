<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Event;
use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Venue;
use TheatreCMS\Models\Work;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\SeoDescriptionResolver;

class SeoDescriptionResolverTest extends TestCase
{
    private SeoDescriptionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SeoDescriptionResolver(new EditorJsHtmlConverter());
    }

    private function editorJsPayload(string $text): string
    {
        return json_encode([
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['text' => $text]],
            ],
        ]);
    }

    public function testProductionPrefersExcerptOverDescription(): void
    {
        $season = new Season('2026', '2026 Season');
        $production = new Production('Hamlet', $season);
        $production->setExcerpt('A short teaser.');
        $production->setDescription($this->editorJsPayload('The full body copy.'));

        $this->assertSame('A short teaser.', $this->resolver->resolve($production));
    }

    public function testProductionFallsBackToDescriptionWhenNoExcerpt(): void
    {
        $season = new Season('2026', '2026 Season');
        $production = new Production('Hamlet', $season);
        $production->setDescription($this->editorJsPayload('The full body copy.'));

        $this->assertSame('The full body copy.', $this->resolver->resolve($production));
    }

    public function testWorkPrefersSynopsisOverDescription(): void
    {
        $work = new Work();
        $work->setSynopsis('A short synopsis.');
        $work->setDescription($this->editorJsPayload('The full body copy.'));

        $this->assertSame('A short synopsis.', $this->resolver->resolve($work));
    }

    public function testWorkFallsBackToDescriptionWhenNoSynopsis(): void
    {
        $work = new Work();
        $work->setDescription($this->editorJsPayload('The full body copy.'));

        $this->assertSame('The full body copy.', $this->resolver->resolve($work));
    }

    public function testSeasonUsesTruncatedOverview(): void
    {
        $season = new Season('2026', '2026 Season');
        $season->setOverview($this->editorJsPayload('The season overview.'));

        $this->assertSame('The season overview.', $this->resolver->resolve($season));
    }

    public function testPageUsesTruncatedContent(): void
    {
        $page = new Page('About', ContentStatus::PUBLISHED, $this->editorJsPayload('Page body text.'));

        $this->assertSame('Page body text.', $this->resolver->resolve($page));
    }

    public function testPostUsesTruncatedContent(): void
    {
        $post = new Post('News', ContentStatus::PUBLISHED, $this->editorJsPayload('Post body text.'));

        $this->assertSame('Post body text.', $this->resolver->resolve($post));
    }

    public function testPersonUsesPlainTextBiography(): void
    {
        $person = new Person();
        $person->setBiography('<p>A talented performer.</p>');

        $this->assertSame('A talented performer.', $this->resolver->resolve($person));
    }

    public function testVenueUsesPlainDescription(): void
    {
        $venue = new Venue('Main Stage', '123 Main St', 'Anytown', 'ST', '00000');
        $venue->setDescription('A historic downtown venue.');

        $this->assertSame('A historic downtown venue.', $this->resolver->resolve($venue));
    }

    public function testEventResolvesToEmptyString(): void
    {
        $event = new Event(new \DateTimeImmutable(), 'scheduled');
        $event->setNotes('Internal-only house notes.');

        $this->assertSame('', $this->resolver->resolve($event));
    }

    public function testSponsorResolvesToEmptyString(): void
    {
        $sponsor = new Sponsor();
        $sponsor->setName('Acme Corp');

        $this->assertSame('', $this->resolver->resolve($sponsor));
    }

    public function testUnmappedEntityResolvesToEmptyString(): void
    {
        $this->assertSame('', $this->resolver->resolve(new \stdClass()));
    }

    public function testTruncatesLongTextOnWordBoundaryWithEllipsis(): void
    {
        $venue = new Venue('Main Stage', '123 Main St', 'Anytown', 'ST', '00000');
        $venue->setDescription(str_repeat('word ', 60));

        $result = $this->resolver->resolve($venue);

        $this->assertLessThanOrEqual(161, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }
}
