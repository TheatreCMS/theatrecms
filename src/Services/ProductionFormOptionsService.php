<?php

namespace TheatreCMS\Services;

use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Repositories\WorkRepository;

/**
 * Supplies the picker/dropdown option lists (seasons, people, works,
 * sponsors, venues, events) used by the production admin create/edit
 * forms, so ProductionController doesn't need each repository injected
 * individually just to call fetchAll().
 */
class ProductionFormOptionsService
{
    public function __construct(
        private readonly SeasonRepository $seasonRepo,
        private readonly PersonRepository $personRepo,
        private readonly WorkRepository $worksRepo,
        private readonly SponsorRepository $sponsorRepo,
        private readonly VenueRepository $venueRepo,
        private readonly EventRepository $eventRepo,
    ) {
    }

    public function getSeasons(): array
    {
        return $this->seasonRepo->fetchAll();
    }

    public function getPeople(): array
    {
        return $this->personRepo->fetchAll();
    }

    public function getWorks(): array
    {
        return $this->worksRepo->fetchAll();
    }

    public function getSponsors(): array
    {
        return $this->sponsorRepo->fetchAll();
    }

    public function getVenues(): array
    {
        return $this->venueRepo->fetchAll();
    }

    public function getEventsForProduction(int $productionId): array
    {
        return $this->eventRepo->fetchByProduction($productionId);
    }
}
