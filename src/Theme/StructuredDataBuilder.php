<?php

namespace TheatreCMS\Theme;

use Clubdeuce\Schema\CreativeWork;
use Clubdeuce\Schema\EventSeries;
use Clubdeuce\Schema\Offer;
use Clubdeuce\Schema\Person as SchemaPerson;
use Clubdeuce\Schema\Place;
use Clubdeuce\Schema\PostalAddress;
use Clubdeuce\Schema\TheaterEvent;
use DateTime;
use TheatreCMS\Models\Event as Performance;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;
use TheatreCMS\Settings\SiteSettings;

class StructuredDataBuilder
{
    public function __construct(private readonly SiteSettings $siteSettings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forSeason(Season $season): array
    {
        $series = new EventSeries();
        $series->setName($season->getLabel());

        if ($season->getOverview() !== '') {
            $series->setDescription($season->getOverview());
        }

        if ($season->getFeaturedImageUrl()) {
            $series->setImageUrl($season->getFeaturedImageUrl());
        }

        $series->setUrl($this->url('/seasons/' . $season->getSlug()));

        foreach ($season->getProductions() as $production) {
            $series->addSubEvent($this->buildTheaterEvent($production));
        }

        return $series->schema();
    }

    /**
     * @param iterable<Performance> $performances
     * @return array<string, mixed>
     */
    public function forProduction(Production $production, iterable $performances = []): array
    {
        return $this->buildTheaterEvent($production, $performances)->schema();
    }

    /**
     * @return array<string, mixed>
     */
    public function forPerson(Person $person): array
    {
        return $this->buildPerson($person)->schema();
    }

    /**
     * @return array<string, mixed>
     */
    public function forWork(Work $work): array
    {
        return $this->buildCreativeWork($work)->schema();
    }

    /**
     * @param iterable<Performance> $performances
     */
    private function buildTheaterEvent(Production $production, iterable $performances = []): TheaterEvent
    {
        $event = new TheaterEvent();
        $event->setName($production->getName());

        if ($production->getExcerpt() !== '') {
            $event->setDescription($production->getExcerpt());
        }

        if ($production->hasFeaturedImage()) {
            $event->setImageUrl($production->getFeaturedImageUrl());
        }

        $event->setUrl($this->productionUrl($production));

        $place = $this->buildPlace($production);
        if ($place !== null) {
            $event->setPlace($place);
        }

        foreach ($production->getPerformers() as $productionPerson) {
            $event->addPerformer($this->buildPerson($productionPerson->getPerson()));
        }

        foreach ($this->findDirectors($production) as $director) {
            $event->addDirector($director);
        }

        $worksPerformed = $this->buildWorksPerformed($production);
        foreach ($worksPerformed as $work) {
            $event->addWorkPerformed($work);
        }

        $performances = is_array($performances) ? $performances : iterator_to_array($performances);

        if (count($performances) > 0) {
            foreach ($performances as $performance) {
                $event->addSubEvent($this->buildPerformanceSubEvent($production, $performance, $place, $worksPerformed));
            }

            return $event;
        }

        if ($production->getOpening() !== null) {
            $event->setStartDate($production->getOpening());
        }

        if ($production->getClosing() !== null) {
            $event->setEndDate($production->getClosing());
        }

        if ($production->getTicketPurchaseUrl() !== '') {
            $offer = new Offer();
            $offer->setUrl($production->getTicketPurchaseUrl());
            $event->addOffer($offer);
        }

        return $event;
    }

    /**
     * @param CreativeWork[] $worksPerformed
     */
    private function buildPerformanceSubEvent(Production $production, Performance $performance, ?Place $place, array $worksPerformed): TheaterEvent
    {
        $subEvent = new TheaterEvent();
        $subEvent->setName($performance->getTitle() ?: $production->getName());
        $subEvent->setUrl($this->productionUrl($production));
        $subEvent->setStartDate(DateTime::createFromImmutable($performance->getStartsAt()));
        $subEvent->setEventStatus($this->mapEventStatus($performance->getStatus()));

        if ($place !== null) {
            $subEvent->setPlace($place);
        }

        foreach ($worksPerformed as $work) {
            $subEvent->addWorkPerformed($work);
        }

        $ticketUrl = $performance->getTicketUrl() ?: $production->getTicketPurchaseUrl();
        if ($ticketUrl) {
            $offer = new Offer();
            $offer->setUrl($ticketUrl);
            $subEvent->addOffer($offer);
        }

        return $subEvent;
    }

    private function buildPlace(Production $production): ?Place
    {
        $venue = $production->getVenue();

        if ($venue === null) {
            return null;
        }

        $place = new Place(['name' => $venue->getName()]);
        $place->setAddress(new PostalAddress([
            'streetAddress' => $venue->getAddress(),
            'addressLocality' => $venue->getCity(),
            'addressRegion' => $venue->getState(),
            'postalCode' => $venue->getPostcode(),
        ]));

        return $place;
    }

    /**
     * @return SchemaPerson[]
     */
    private function findDirectors(Production $production): array
    {
        $directors = [];

        foreach ([$production->getProductionTeam(), $production->getCreativeTeam()] as $team) {
            foreach ($team as $productionPerson) {
                if (stripos($productionPerson->getRole() ?? '', 'director') !== false) {
                    $directors[] = $this->buildPerson($productionPerson->getPerson());
                }
            }
        }

        return $directors;
    }

    private function buildPerson(Person $person): SchemaPerson
    {
        $schemaPerson = new SchemaPerson(['name' => $person->getName()]);

        if ($person->getBiography() !== '') {
            $schemaPerson->setDescription($person->getBiography());
        }

        if ($person->getHeadshotUrl() !== '') {
            $schemaPerson->setImageUrl($person->getHeadshotUrl());
        }

        $schemaPerson->setUrl($this->url('/people/' . $person->getSlug()));

        return $schemaPerson;
    }

    /**
     * @return CreativeWork[]
     */
    private function buildWorksPerformed(Production $production): array
    {
        $works = [];

        foreach ($production->getWorks() as $work) {
            $works[] = $this->buildCreativeWork($work);
        }

        return $works;
    }

    private function buildCreativeWork(Work $work): CreativeWork
    {
        $creativeWork = new CreativeWork(['name' => $work->getTitle()]);

        if ($work->getSynopsis() !== '') {
            $creativeWork->setDescription($work->getSynopsis());
        }

        $creativeWork->setUrl($this->url('/works/' . $work->getSlug()));

        foreach ($work->getCreators() as $creator) {
            $creativeWork->addAuthor($this->buildPerson($creator));
        }

        return $creativeWork;
    }

    private function mapEventStatus(string $status): string
    {
        return match (strtolower($status)) {
            'cancelled', 'canceled' => 'EventCancelled',
            'postponed' => 'EventPostponed',
            'rescheduled' => 'EventRescheduled',
            default => 'EventScheduled',
        };
    }

    private function productionUrl(Production $production): string
    {
        return $this->url('/seasons/' . $production->getSeason()->getSlug() . '/' . $production->getSlug());
    }

    private function url(string $path): string
    {
        $base = rtrim((string) $this->siteSettings->get('site_url', ''), '/');

        return $base . $path;
    }
}
