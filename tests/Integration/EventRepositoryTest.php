<?php

namespace TheatreCMS\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Event;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\EventRepository
 */
class EventRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private EventRepository $repository;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->repository = new EventRepository($this->em);
    }

    public function testFetchPublicListsOnlyUpcomingEventsSoonestFirst(): void
    {
        $this->repository->create(['title' => 'Last Season', 'startsAt' => new DateTimeImmutable('-1 week')]);
        $this->repository->create(['title' => 'After-Show Talk', 'startsAt' => new DateTimeImmutable('+2 weeks')]);
        $this->repository->create(['title' => 'Opening Party', 'startsAt' => new DateTimeImmutable('+1 week')]);

        $titles = array_map(static fn(Event $event) => $event->getTitle(), $this->repository->fetchPublic());

        $this->assertSame(['Opening Party', 'After-Show Talk'], $titles);
    }

    public function testPastEventsKeepTheirSinglePages(): void
    {
        $event = $this->repository->create(['title' => 'Last Season', 'startsAt' => new DateTimeImmutable('-1 week')]);

        $this->assertSame($event, $this->repository->fetchPublicBySlug($event->getSlug()));
    }
}
