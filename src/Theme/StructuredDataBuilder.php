<?php

namespace TheatreCMS\Theme;

use Clubdeuce\Schema\CreativeWork;
use Clubdeuce\Schema\EventSeries;
use Clubdeuce\Schema\Organization;
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
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Work;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Text\EditorJsHtmlConverter;

class StructuredDataBuilder
{
    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly EditorJsHtmlConverter $editorJsHtmlConverter
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forSeason(Season $season): array
    {
        $series = new EventSeries();
        $series->setName($season->getLabel());
        $series->setUrl($this->url('/seasons/' . $season->getSlug()));

        $overview = $this->plainTextFromEditorJs($season->getOverview());
        if ($overview !== '') {
            $series->setDescription($overview);
        }

        if ($season->getFeaturedImageUrl()) {
            $series->setImageUrl($season->getFeaturedImageUrl());
        }

        if ($season->getSponsorships() !== null) {
            foreach ($season->getSponsorships() as $sponsorship) {
                $series->addSponsor($this->buildSponsorOrganization($sponsorship->getSponsor()));
            }
        }

        foreach ($season->getProductions() as $production) {
            $subEvent = $this->buildTheaterEventForProduction($production);
            $series->addSubEvent($subEvent);
        }

        return $series->schema();
    }

    /**
     * @param iterable<Performance> $performances
     * @return array<string, mixed>
     */
    public function forProduction(Production $production, iterable $performances = []): array
    {
        $series = new EventSeries();

        $series->setName($production->getName())
            ->setStartDate($production->getOpening())
            ->setEndDate($production->getClosing())
            ->setSubEvents($this->buildTheaterSubEvents($production, $performances));

        return $series->schema();
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

    private function buildTheaterSubEvents(Production $production, iterable $performances = []): array
    {
        $performances = is_array($performances) ? $performances : iterator_to_array($performances);

        if (count($performances) > 0) {
            $events = [];

            foreach ($performances as $performance) {
                $events[] = $this->buildTheaterEvent($production, $performance);
            }

            return $events;
        }

        return [];
    }
    /**
     * @param iterable<Performance> $performances
     */
    private function buildTheaterEvent(Production $production, Performance $performance): TheaterEvent
    {
        $event = new TheaterEvent();
        $event->setName($production->getName())
            ->setDescription($this->plainTextFromEditorJs($production->getDescription()))
            ->setUrl($this->productionUrl($production))
            ->setPlace($this->buildPlace($production));

        if ($performance->getStartsAt() !== null) {
            $mutable = DateTime::createFromImmutable($performance->getStartsAt());
            $event->setStartDate($mutable);
        }

        $description = $this->plainTextFromEditorJs($production->getDescription());

        if ($description !== '') {
            $event->setDescription($description);
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

        foreach ($production->getWorks() as $work) {
            $event->addWork($this->buildCreativeWork($work));
        }

        $ticketUrl = $performance->getEffectiveTicketUrl();

        if ($ticketUrl !== null && $ticketUrl !== '') {
            $offer = new Offer();
            $offer->setUrl($ticketUrl);
            $event->addOffer($offer);
        }

        return $event;
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

    /**
     * @todo Fix this to use a permalink-style nethodology vs the hardcoded pathing.
     */
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

    private function buildSponsorOrganization(Sponsor $sponsor): Organization
    {
        $organization = new Organization(['name' => $sponsor->getName()]);

        if ($sponsor->getWebsiteUrl() !== null && $sponsor->getWebsiteUrl() !== '') {
            $organization->setUrl($sponsor->getWebsiteUrl());
        }

        if ($sponsor->getLogoUrl() !== null && $sponsor->getLogoUrl() !== '') {
            $organization->setImageUrl($sponsor->getLogoUrl());
        }

        return $organization;
    }

    /**
     * Convert an EditorJS payload (as stored in the database) into the plain
     * text schema.org's `description` (Text) property expects.
     */
    private function plainTextFromEditorJs(?string $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }

        $html = $this->editorJsHtmlConverter->toHtml($payload);
        if ($html === '') {
            return '';
        }

        // Turn block/line boundaries into newlines before stripping tags, so
        // separate paragraphs/list items/headings don't run together.
        $text = preg_replace('/<(br)\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/(p|h[1-6]|li|blockquote|pre)>/i', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]*\n+/', "\n\n", (string) $text);

        return trim((string) $text);
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

    private function buildSeriesForProduction(Production $production): EventSeries{

        $series = new EventSeries();

        $series->setName($production->getName())
            ->setStartDate($production->getOpening())
            ->setEndDate($production->getClosing())
            ->setSubEvents($this->buildTheaterSubEvents($production, $production->getPerformances()));

        return $series;
    }

    private function buildTheaterEventForProduction(Production $production): TheaterEvent {
        $event = new TheaterEvent();

        $performers = $production->getPerformers();

        foreach($performers as $performer) {
            $event->addPerformer($this->buildPerson($performer->getPerson()));
        }

        $event->setName($production->getName())
            ->setDescription($this->plainTextFromEditorJs($production->getDescription()))
            ->setUrl($this->productionUrl($production))
            ->setPlace($this->buildPlace($production))
            ->setStartDate($production->getOpening())
            ->setEndDate($production->getClosing());

        return $event;
    }

}
