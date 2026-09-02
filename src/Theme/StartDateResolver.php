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

    public function resolveProductionStart(Production $production): ?\DateTime
    {
        if ($production->getOpening()) {
            return $production->getOpening();
        }

        $performances = $production->getPerformances();

        if ($performances->count()) {
            $startDate = null;

            while (null === $startDate) {
                $startDate = $performances->current()->startDate();
                $performances->next();
            }

            return $startDate;
        }

        return null;
    }

    public function resolveSeasonStart(Season $season): ?\DateTime
    {
        if ($season->getStartDate()) {
            return $season->getStartDate();
        }

        $productions = $season->getProductions();

        if ($productions->count()) {
            $startDate = null;
            while (null === $startDate) {
                $startDate = $this->resolveProductionStart($productions->current());
                $productions->next();
            }

            return $startDate;
        }

        return null;
    }
}
