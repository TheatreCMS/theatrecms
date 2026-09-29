<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\EventRepository
 */
class EventRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private EventRepository $events;
    private Production $production;
    private Venue $venue;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $meta = new ContentMetaRepository($this->em);
        $season = (new SeasonRepository($this->em, $meta))
            ->create(['label' => '2026-27', 'startDate' => '2026-09-01', 'endDate' => '2027-06-01']);
        $this->production = (new ProductionRepository($this->em, $meta))->create([
            'name' => 'Hamlet',
            'seasonId' => $season->getId(),
            'opening' => '2026-10-01',
            'closing' => '2026-10-11',
        ]);
        $this->venue = (new VenueRepository($this->em, $meta))->create([
            'name' => 'Main Stage',
            'address' => '1 Stage Rd',
            'city' => 'Testville',
            'state' => 'TS',
            'postcode' => '00000',
        ]);
        $this->events = new EventRepository($this->em);
    }

    public function testCreatePersistsAllFieldsAndSlugsFromTitle(): void
    {
        $event = $this->events->create([
            'productionId' => $this->production->getId(),
            'venueId' => $this->venue->getId(),
            'startsAt' => '2026-10-01 19:30',
            'endsAt' => new \DateTime('2026-10-01 22:00'),
            'status' => 'cancelled',
            'ticketUrl' => 'https://tickets.test',
            'notes' => 'Talkback',
            'title' => 'Preview',
        ]);

        $this->assertSame('2026-10-01-preview', $event->getSlug());
        $this->assertSame($this->production, $event->getProduction());
        $this->assertSame($this->venue, $event->getVenue());
        $this->assertSame('2026-10-01 22:00', $event->getEndsAt()->format('Y-m-d H:i'));
        $this->assertSame('cancelled', $event->getStatus());
        $this->assertSame('https://tickets.test', $event->getTicketUrl());
        $this->assertSame('Talkback', $event->getNotes());
    }

    public function testCreateSlugsFromProductionNameOrTimestamp(): void
    {
        $withProduction = $this->events->create([
            'productionId' => $this->production->getId(),
            'startsAt' => new \DateTimeImmutable('2026-10-02 20:00'),
            'endsAt' => new \DateTimeImmutable('2026-10-02 22:00'),
        ]);
        $standalone = $this->events->create(['startsAt' => new \DateTime('2026-11-05 18:15:00')]);

        $this->assertSame('2026-10-02-hamlet', $withProduction->getSlug());
        $this->assertSame('2026-11-05-181500', $standalone->getSlug());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCreateProvider(): array
    {
        return [
            'missing start' => [[], 'Start date/time is required.'],
            'bad start' => [['startsAt' => 'not a date'], 'Invalid startsAt date format.'],
            'bad end' => [['startsAt' => '2026-10-01', 'endsAt' => 'not a date'], 'Invalid endsAt date format.'],
            'unknown production' => [['startsAt' => '2026-10-01', 'productionId' => 999], 'Production not found.'],
            'unknown venue' => [['startsAt' => '2026-10-01', 'venueId' => 999], 'Venue not found.'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    #[DataProvider('invalidCreateProvider')]
    public function testCreateRejectsInvalidInput(array $args, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->events->create($args);
    }

    public function testUpdateBackfillsMissingSlug(): void
    {
        $event = $this->events->create(['startsAt' => '2026-10-01 19:30', 'title' => 'Gala']);
        $event->setTitle('Opening')->setSlug('');

        $this->events->update($event);

        $this->assertSame('2026-10-01-opening', $event->getSlug());
    }

    public function testFetchByProductionReturnsNewestFirst(): void
    {
        $this->events->create(['productionId' => $this->production->getId(), 'startsAt' => '2026-10-01 19:30']);
        $this->events->create(['productionId' => $this->production->getId(), 'startsAt' => '2026-10-03 19:30']);
        $this->events->create(['startsAt' => '2026-10-05 19:30']);

        $dates = array_map(
            fn(Event $e) => $e->getStartsAt()->format('Y-m-d'),
            $this->events->fetchByProduction($this->production->getId())
        );

        $this->assertSame(['2026-10-03', '2026-10-01'], $dates);
    }

    public function testCreateRecurringSkipsExistingPerformances(): void
    {
        $this->events->create(['productionId' => $this->production->getId(), 'startsAt' => '2026-10-02 20:00']);

        $result = $this->events->createRecurring([
            'productionId' => $this->production->getId(),
            'venueId' => $this->venue->getId(),
            'weekdays' => ['friday' => '20:00', 'Saturday' => '2:30pm'],
            'ticketUrl' => 'https://tickets.test',
        ]);

        $this->assertSame(['created' => 3, 'skipped' => 1], $result);
        $this->assertCount(4, $this->events->fetchByProduction($this->production->getId()));

        $again = $this->events->createRecurring([
            'productionId' => $this->production->getId(),
            'weekdays' => ['friday' => '20:00'],
        ]);
        $this->assertSame(['created' => 0, 'skipped' => 2], $again);
    }

    public function testCreateRecurringRequiresProductionWithDates(): void
    {
        try {
            $this->events->createRecurring(['weekdays' => ['friday' => '20:00']]);
            $this->fail('Expected missing production to be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Production not found.', $e->getMessage());
        }

        $this->production->setClosing(null);
        $this->expectExceptionMessage('Production must have opening and closing dates before adding recurring performances.');
        $this->events->createRecurring(['productionId' => $this->production->getId(), 'weekdays' => ['friday' => '20:00']]);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidRecurringProvider(): array
    {
        return [
            'unknown weekday' => [['funday' => '20:00'], '2026-10-11', 'Invalid weekday selection: funday.'],
            'blank time' => [['friday' => ' '], '2026-10-11', 'A start time is required for Friday.'],
            'bad time' => [['friday' => 'late'], '2026-10-11', 'Invalid start time for Friday.'],
            'closing before opening' => [['friday' => '20:00'], '2026-09-01', 'Production closing date must be on or after the opening date.'],
        ];
    }

    /**
     * @param array<string, string> $weekdays
     */
    #[DataProvider('invalidRecurringProvider')]
    public function testBuildRecurringStartsAtRejectsInvalidSelections(array $weekdays, string $closing, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        EventRepository::buildRecurringStartsAt(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable($closing), $weekdays);
    }
}
