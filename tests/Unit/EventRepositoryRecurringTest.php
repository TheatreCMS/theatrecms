<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Repositories\EventRepository;

class EventRepositoryRecurringTest extends TestCase
{
    public function testBuildRecurringStartsAtCreatesExpectedOccurrences(): void
    {
        $opening = new \DateTimeImmutable('2026-10-01');
        $closing = new \DateTimeImmutable('2026-10-11');

        $startsAtValues = EventRepository::buildRecurringStartsAt($opening, $closing, [
            'thursday' => '20:00',
            'friday' => '20:00',
            'saturday' => '20:00',
            'sunday' => '19:30',
        ]);

        $this->assertSame([
            '2026-10-01 20:00',
            '2026-10-02 20:00',
            '2026-10-03 20:00',
            '2026-10-04 19:30',
            '2026-10-08 20:00',
            '2026-10-09 20:00',
            '2026-10-10 20:00',
            '2026-10-11 19:30',
        ], array_map(
            static fn(\DateTimeImmutable $startsAt): string => $startsAt->format('Y-m-d H:i'),
            $startsAtValues
        ));
    }

    public function testBuildRecurringStartsAtRejectsEmptySelections(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Select at least one weekday for recurring performances.');

        EventRepository::buildRecurringStartsAt(
            new \DateTimeImmutable('2026-10-01'),
            new \DateTimeImmutable('2026-10-11'),
            []
        );
    }

    public function testBuildRecurringStartsAtRejectsSelectionsWithNoMatchingDates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No dates in the production run match the selected weekdays.');

        EventRepository::buildRecurringStartsAt(
            new \DateTimeImmutable('2026-10-06'),
            new \DateTimeImmutable('2026-10-06'),
            [
                'thursday' => '20:00',
            ]
        );
    }
}
