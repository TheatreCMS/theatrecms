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
use TheatreCMS\Theme\TitleResolver;

class TitleResolverTest extends TestCase
{
    private TitleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TitleResolver();
    }

    public function testResolvesPageTitle(): void
    {
        $page = new Page('About Us', ContentStatus::PUBLISHED, 'body');

        $this->assertSame('About Us', $this->resolver->resolve($page));
    }

    public function testResolvesPostTitle(): void
    {
        $post = new Post('Breaking News', ContentStatus::PUBLISHED, 'body');

        $this->assertSame('Breaking News', $this->resolver->resolve($post));
    }

    public function testResolvesWorkTitle(): void
    {
        $work = new Work();
        $work->setTitle('Romeo and Juliet');

        $this->assertSame('Romeo and Juliet', $this->resolver->resolve($work));
    }

    public function testResolvesProductionName(): void
    {
        $season = new Season('2026', '2026 Season');
        $production = new Production('Hamlet', $season);

        $this->assertSame('Hamlet', $this->resolver->resolve($production));
    }

    public function testResolvesSeasonLabel(): void
    {
        $season = new Season('2026', '2026 Season');

        $this->assertSame('2026 Season', $this->resolver->resolve($season));
    }

    public function testResolvesPersonName(): void
    {
        $person = new Person();
        $person->setFirstName('Jane');
        $person->setLastName('Doe');

        $this->assertSame('Jane Doe', $this->resolver->resolve($person));
    }

    public function testResolvesVenueName(): void
    {
        $venue = new Venue('Main Stage', '123 Main St', 'Anytown', 'ST', '00000');

        $this->assertSame('Main Stage', $this->resolver->resolve($venue));
    }

    public function testResolvesSponsorName(): void
    {
        $sponsor = new Sponsor();
        $sponsor->setName('Acme Corp');

        $this->assertSame('Acme Corp', $this->resolver->resolve($sponsor));
    }

    public function testResolvesEventTitleWhenSet(): void
    {
        $event = new Event(new \DateTimeImmutable(), 'scheduled', null, 'Opening Night Gala');

        $this->assertSame('Opening Night Gala', $this->resolver->resolve($event));
    }

    public function testResolvesEventWithNoTitleToEmptyString(): void
    {
        $event = new Event(new \DateTimeImmutable(), 'scheduled');

        $this->assertSame('', $this->resolver->resolve($event));
    }

    public function testThrowsForUnmappedEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver->resolve(new \stdClass());
    }
}
