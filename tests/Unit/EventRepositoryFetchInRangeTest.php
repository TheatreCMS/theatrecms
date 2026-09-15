<?php

namespace TheatreCMS\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\EventRepository;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\EventRepository
 */
class EventRepositoryFetchInRangeTest extends TestCase
{
    private EntityManager $em;
    private EventRepository $repository;
    private Production $production;
    private Venue $venue;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->repository = new EventRepository($this->em);

        $season = new Season('2025-2026', '2025-2026 Season');
        $this->venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $this->venue->setSlug('main-stage');
        $this->production = new Production('A Test Play', $season);
        $this->production->setSlug('a-test-play');
        $this->production->setVenue($this->venue);

        $this->em->persist($season);
        $this->em->persist($this->venue);
        $this->em->persist($this->production);
        $this->em->flush();
    }

    private function createEvent(string $startsAt, ?Production $production = null): Event
    {
        $event = new Event(new DateTimeImmutable($startsAt), 'scheduled', $production);
        $event->setSlug('event-' . $startsAt);

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function testReturnsEventsWithinRangeOrderedAscending(): void
    {
        $inRangeLater = $this->createEvent('2026-03-10 19:30:00', $this->production);
        $inRangeEarlier = $this->createEvent('2026-03-05 19:30:00', $this->production);
        $this->createEvent('2026-02-01 19:30:00', $this->production);
        $this->createEvent('2026-04-01 19:30:00', $this->production);

        $results = $this->repository->fetchInRange(
            new DateTimeImmutable('2026-03-01 00:00:00'),
            new DateTimeImmutable('2026-03-31 23:59:59')
        );

        $this->assertCount(2, $results);
        $this->assertSame($inRangeEarlier->getId(), $results[0]->getId());
        $this->assertSame($inRangeLater->getId(), $results[1]->getId());
    }

    public function testIncludesEventsAtRangeBoundaries(): void
    {
        $start = $this->createEvent('2026-03-01 00:00:00', $this->production);
        $end = $this->createEvent('2026-03-31 23:59:59', $this->production);

        $results = $this->repository->fetchInRange(
            new DateTimeImmutable('2026-03-01 00:00:00'),
            new DateTimeImmutable('2026-03-31 23:59:59')
        );

        $ids = array_map(fn (Event $event) => $event->getId(), $results);
        $this->assertContains($start->getId(), $ids);
        $this->assertContains($end->getId(), $ids);
    }

    public function testIncludesCancelledEventsRatherThanFilteringThem(): void
    {
        $cancelled = new Event(new DateTimeImmutable('2026-03-15 19:30:00'), 'cancelled', $this->production);
        $cancelled->setSlug('cancelled-event');
        $this->em->persist($cancelled);
        $this->em->flush();

        $results = $this->repository->fetchInRange(
            new DateTimeImmutable('2026-03-01 00:00:00'),
            new DateTimeImmutable('2026-03-31 23:59:59')
        );

        $this->assertCount(1, $results);
        $this->assertSame('cancelled', $results[0]->getStatus());
    }

    public function testEagerLoadsProductionSeasonAndVenue(): void
    {
        $this->createEvent('2026-03-10 19:30:00', $this->production);

        $results = $this->repository->fetchInRange(
            new DateTimeImmutable('2026-03-01 00:00:00'),
            new DateTimeImmutable('2026-03-31 23:59:59')
        );

        $this->em->clear();

        $this->assertNotNull($results[0]->getProduction());
        $this->assertSame('a-test-play', $results[0]->getProduction()->getSlug());
        $this->assertSame('2025-2026', $results[0]->getProduction()->getSeason()->getSlug());
    }

    public function testReturnsEmptyArrayWhenNoEventsInRange(): void
    {
        $this->createEvent('2026-01-01 19:30:00', $this->production);

        $results = $this->repository->fetchInRange(
            new DateTimeImmutable('2026-03-01 00:00:00'),
            new DateTimeImmutable('2026-03-31 23:59:59')
        );

        $this->assertSame([], $results);
    }
}
