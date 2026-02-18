<?php

namespace Clubdeuce\TheatreCMS\ValueObjects;

readonly class DateRange
{
    public function __construct(private \DateTimeImmutable $start, private \DateTimeImmutable $end)
    {
        if ($end <= $start)
            throw new \InvalidArgumentException('End date must be after start date.');
    }

    public function getStart(): \DateTimeImmutable {
        return $this->start;
    }

    public function getEnd(): \DateTimeImmutable {
        return $this->end;
    }
}
