<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;

/**
 * This class is used to generate the start date for either seasons,
 * productions, or events.
 */
class StartDateResolver
{
    public function resolve(mixed $entity): ?\DateTime
    {
        $startDate = match (true) {
            $entity instanceof Season     => $this->resolveSeasonStart($entity),
            $entity instanceof Production => $this->resolveProductionStart($entity),
            default => null,
        };

        return $startDate;
    }

    /**
     * The production's opening date, falling back to its earliest performance.
     */
    public function resolveProductionStart(Production $production): ?\DateTime
    {
        if ($production->getOpening()) {
            return $production->getOpening();
        }

        $earliest = null;

        foreach ($production->getPerformances() as $performance) {
            if ($earliest === null || $performance->getStartsAt() < $earliest) {
                $earliest = $performance->getStartsAt();
            }
        }

        return $earliest ? \DateTime::createFromImmutable($earliest) : null;
    }

    /**
     * The season's start date, falling back to the earliest start among its productions.
     */
    public function resolveSeasonStart(Season $season): ?\DateTime
    {
        if ($season->getStartDate()) {
            return $season->getStartDate();
        }

        $earliest = null;

        foreach ($season->getProductions() as $production) {
            $startDate = $this->resolveProductionStart($production);

            if ($startDate !== null && ($earliest === null || $startDate < $earliest)) {
                $earliest = $startDate;
            }
        }

        return $earliest;
    }
}
