<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Venue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class EventRepository extends BaseRepository
{
    private const WEEKDAY_MAP = [
        'sunday'    => 0,
        'monday'    => 1,
        'tuesday'   => 2,
        'wednesday' => 3,
        'thursday'  => 4,
        'friday'    => 5,
        'saturday'  => 6,
    ];

    protected string $entityClass = Event::class;

    public function create(array $args): Event
    {
        $args = array_merge([
            'productionId' => null,
            'venueId' => null,
            'startsAt' => null,
            'endsAt' => null,
            'status' => 'scheduled',
            'ticketUrl' => null,
            'notes' => null,
            'title' => null,
        ], $args);

        $event = $this->createEventEntity(
            $args,
            $this->resolveProduction($args['productionId']),
            $this->resolveVenue($args['venueId'])
        );

        $event->setSlug($this->generateEventSlug($event));

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    /**
     * @return array{created:int,skipped:int}
     */
    public function createRecurring(array $args): array
    {
        $args = array_merge([
            'productionId' => null,
            'weekdays' => [],
            'status' => 'scheduled',
            'venueId' => null,
            'ticketUrl' => null,
            'notes' => null,
            'title' => null,
        ], $args);

        $production = $this->resolveProduction($args['productionId']);

        if (!$production) {
            throw new \InvalidArgumentException('Production not found.');
        }

        $opening = $production->getOpening();
        $closing = $production->getClosing();

        if (!$opening || !$closing) {
            throw new \InvalidArgumentException(
                'Production must have opening and closing dates before adding recurring performances.'
            );
        }

        $startsAtValues = self::buildRecurringStartsAt(
            $opening,
            $closing,
            is_array($args['weekdays']) ? $args['weekdays'] : []
        );

        $existingStartKeys = $this->fetchExistingProductionStartKeys(
            $production,
            new \DateTimeImmutable($opening->format('Y-m-d 00:00:00')),
            new \DateTimeImmutable($closing->format('Y-m-d 23:59:59'))
        );

        $venue = $this->resolveVenue($args['venueId']);
        $created = 0;
        $skipped = 0;

        foreach ($startsAtValues as $startsAt) {
            $startKey = $startsAt->format('Y-m-d H:i:s');

            if (isset($existingStartKeys[$startKey])) {
                $skipped++;
                continue;
            }

            $event = $this->createEventEntity([
                'startsAt' => $startsAt,
                'status' => $args['status'],
                'ticketUrl' => $args['ticketUrl'],
                'notes' => $args['notes'],
                'title' => $args['title'],
            ], $production, $venue);

            $event->setSlug($this->generateEventSlug($event));

            $this->em->persist($event);
            $existingStartKeys[$startKey] = true;
            $created++;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function update($event): void
    {
        if (empty($event->getSlug())) {
            $event->setSlug($this->generateEventSlug($event));
        }

        parent::update($event);
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.startsAt', $alias), 'DESC');
    }

    /**
     * @return Event[]
     */
    public function fetchByProduction(int $productionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.production = :productionId')
            ->setParameter('productionId', $productionId)
            ->orderBy('e.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public static function buildRecurringStartsAt(
        \DateTimeInterface $opening,
        \DateTimeInterface $closing,
        array $weekdaySelections
    ): array {
        if ($weekdaySelections === []) {
            throw new \InvalidArgumentException('Select at least one weekday for recurring performances.');
        }

        $normalizedSelections = [];

        foreach ($weekdaySelections as $weekday => $time) {
            $normalizedWeekday = strtolower(trim((string) $weekday));
            $normalizedTime = trim((string) $time);

            if (!array_key_exists($normalizedWeekday, self::WEEKDAY_MAP)) {
                throw new \InvalidArgumentException(sprintf('Invalid weekday selection: %s.', $weekday));
            }

            if ($normalizedTime === '') {
                throw new \InvalidArgumentException(
                    sprintf('A start time is required for %s.', ucfirst($normalizedWeekday))
                );
            }

            $normalizedSelections[$normalizedWeekday] = $normalizedTime;
        }

        $openingDate = new \DateTimeImmutable($opening->format('Y-m-d'));
        $closingDate = new \DateTimeImmutable($closing->format('Y-m-d'));

        if ($closingDate < $openingDate) {
            throw new \InvalidArgumentException('Production closing date must be on or after the opening date.');
        }

        $startsAtValues = [];

        for ($current = $openingDate; $current <= $closingDate; $current = $current->modify('+1 day')) {
            $weekdayName = strtolower($current->format('l'));

            if (!isset($normalizedSelections[$weekdayName])) {
                continue;
            }

            [$hour, $minute] = self::parseRecurringTime($normalizedSelections[$weekdayName], $weekdayName);
            $startsAtValues[] = $current->setTime($hour, $minute);
        }

        if ($startsAtValues === []) {
            throw new \InvalidArgumentException('No dates in the production run match the selected weekdays.');
        }

        return $startsAtValues;
    }

    private function generateEventSlug(Event $event): string
    {
        $date = $event->getStartsAt()->format('Y-m-d');

        if (!empty($event->getTitle())) {
            $base = $date . '-' . $event->getTitle();
        } elseif ($event->getProduction() !== null) {
            $base = $date . '-' . $event->getProduction()->getName();
        } else {
            $base = $event->getStartsAt()->format('Y-m-d-His');
        }

        return $this->generateUniqueSlug($base);
    }

    private function createEventEntity(array $args, ?Production $production, ?Venue $venue): Event
    {
        $startsAt = $this->normalizeStartsAt($args['startsAt'] ?? null);
        $event = new Event($startsAt, $args['status'], $production, $args['title'] ?? null);

        if ($venue) {
            $event->setVenue($venue);
        }

        $endsAt = $this->normalizeOptionalDateTime($args['endsAt'] ?? null);
        if ($endsAt !== null) {
            $event->setEndsAt($endsAt);
        }

        if (!empty($args['ticketUrl'])) {
            $event->setTicketUrl($args['ticketUrl']);
        }

        if (!empty($args['notes'])) {
            $event->setNotes($args['notes']);
        }

        if (!empty($args['title'])) {
            $event->setTitle($args['title']);
        }

        return $event;
    }

    private function normalizeStartsAt(mixed $startsAt): \DateTimeImmutable
    {
        if (empty($startsAt)) {
            throw new \InvalidArgumentException('Start date/time is required.');
        }

        if ($startsAt instanceof \DateTimeImmutable) {
            return $startsAt;
        }

        if ($startsAt instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($startsAt);
        }

        try {
            return new \DateTimeImmutable((string) $startsAt);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid startsAt date format.');
        }
    }

    private function normalizeOptionalDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid endsAt date format.');
        }
    }

    private function resolveProduction(mixed $productionId): ?Production
    {
        if (empty($productionId)) {
            return null;
        }

        $production = $this->em->getRepository(Production::class)->find((int) $productionId);

        if (!$production) {
            throw new \InvalidArgumentException('Production not found.');
        }

        return $production;
    }

    private function resolveVenue(mixed $venueId): ?Venue
    {
        if (empty($venueId)) {
            return null;
        }

        $venue = $this->em->getRepository(Venue::class)->find((int) $venueId);

        if (!$venue) {
            throw new \InvalidArgumentException('Venue not found.');
        }

        return $venue;
    }

    /**
     * @return array<string, bool>
     */
    private function fetchExistingProductionStartKeys(
        Production $production,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd
    ): array {
        $events = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.production = :production')
            ->andWhere('e.startsAt BETWEEN :rangeStart AND :rangeEnd')
            ->setParameter('production', $production)
            ->setParameter('rangeStart', $rangeStart)
            ->setParameter('rangeEnd', $rangeEnd)
            ->getQuery()
            ->getResult();

        $existingStartKeys = [];

        foreach ($events as $event) {
            if (!$event instanceof Event) {
                continue;
            }

            $existingStartKeys[$event->getStartsAt()->format('Y-m-d H:i:s')] = true;
        }

        return $existingStartKeys;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function parseRecurringTime(string $time, string $weekday): array
    {
        $timestamp = strtotime($time);

        if ($timestamp === false) {
            throw new \InvalidArgumentException(sprintf('Invalid start time for %s.', ucfirst($weekday)));
        }

        return [
            (int) date('G', $timestamp),
            (int) date('i', $timestamp),
        ];
    }
}
