<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Theme\StartDateResolver;

/**
 * @coversDefaultClass \TheatreCMS\Theme\StartDateResolver
 */
class StartDateResolverTest extends TestCase
{
    public function testProductionOpeningWins(): void
    {
        $production = new Production('Hamlet', new Season('s', 'S'));
        $production->setOpening(new \DateTime('2025-10-01'));
        $this->performance($production, '2025-09-01 19:30');

        $this->assertSame('2025-10-01', (new StartDateResolver())->resolve($production)->format('Y-m-d'));
    }

    public function testProductionFallsBackToEarliestPerformance(): void
    {
        $production = new Production('Hamlet', new Season('s', 'S'));
        $this->performance($production, '2025-10-03 19:30');
        $this->performance($production, '2025-10-01 14:00');

        $start = (new StartDateResolver())->resolve($production);

        $this->assertInstanceOf(\DateTime::class, $start);
        $this->assertSame('2025-10-01 14:00', $start->format('Y-m-d H:i'));
    }

    public function testProductionWithoutOpeningOrPerformancesHasNoStart(): void
    {
        $this->assertNull((new StartDateResolver())->resolve(new Production('Hamlet', new Season('s', 'S'))));
    }

    public function testSeasonFallsBackToEarliestProductionStartSkippingUndated(): void
    {
        $season = new Season('s', 'S');
        $undated = new Production('Undated', $season);
        $later = new Production('Later', $season);
        $later->setOpening(new \DateTime('2025-11-01'));
        $earlier = new Production('Earlier', $season);
        $this->performance($earlier, '2025-10-15 19:30');
        foreach ([$undated, $later, $earlier] as $production) {
            $season->getProductions()->add($production);
        }

        $this->assertSame('2025-10-15', (new StartDateResolver())->resolve($season)->format('Y-m-d'));
    }

    public function testSeasonWithOnlyUndatedProductionsHasNoStart(): void
    {
        $season = new Season('s', 'S');
        $season->getProductions()->add(new Production('Undated', $season));

        $this->assertNull((new StartDateResolver())->resolve($season));
    }

    private function performance(Production $production, string $startsAt): void
    {
        $production->getPerformances()->add(new Event(new \DateTimeImmutable($startsAt), 'scheduled', $production));
    }
}
