<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\ProductionPerson;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Services\ProductionFormOptionsService;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

#[AllowMockObjectsWithoutExpectations]
class ProductionControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private ContentMetaRepository $meta;
    private ProductionRepository $productions;
    private SeasonRepository $seasons;
    private PersonRepository $people;
    private WorkRepository $works;
    private SponsorRepository $sponsors;
    private VenueRepository $venues;
    private Season $season;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->meta = new ContentMetaRepository($this->em);
        $this->productions = new ProductionRepository($this->em, $this->meta);
        $this->seasons = new SeasonRepository($this->em, $this->meta);
        $this->people = new PersonRepository($this->em, $this->meta);
        $this->works = new WorkRepository($this->em);
        $this->sponsors = new SponsorRepository($this->em);
        $this->venues = new VenueRepository($this->em, $this->meta);
        $this->season = $this->seasons->create(['label' => '2025-26', 'startDate' => '2025-09-01', 'endDate' => '2026-06-01']);
    }

    public function testIndexSearchesAndSwitchesTemplate(): void
    {
        $this->production('Hamlet');
        $this->production('Macbeth');

        $this->controller()->index($this->request('GET'), new Response());
        $this->assertSame('admin/productions/index.html.twig', $this->rendered['template']);
        $this->assertCount(2, $this->rendered['data']['productions']);
        $this->assertCount(1, $this->rendered['data']['seasons']);

        $this->controller()->index($this->request('GET', htmx: true, query: ['q' => 'Mac']), new Response());
        $this->assertSame('admin/productions/_list.html.twig', $this->rendered['template']);
    }

    public function testCreateRendersFormOptions(): void
    {
        $this->person('Ada', 'Lovelace');
        $this->sponsors->create(['name' => 'Acme']);
        $this->venue();

        $this->controller()->create($this->request('GET'), new Response());

        $this->assertSame('admin/productions/create.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['people']);
        $this->assertCount(1, $this->rendered['data']['sponsors']);
        $this->assertCount(1, $this->rendered['data']['venues']);
        $this->assertSame([], $this->rendered['data']['works']);
    }

    public function testEditRendersProductionAndActiveTab(): void
    {
        $production = $this->production('Hamlet');
        $this->meta->set('production', $production->getId(), 'hero_image_id', '7');

        $this->controller()->edit($this->request('GET', query: ['tab' => 'performances']), new Response(), ['id' => $production->getId()]);
        $this->assertSame('admin/productions/edit.html.twig', $this->rendered['template']);
        $this->assertSame($production, $this->rendered['data']['production']);
        $this->assertSame('performances', $this->rendered['data']['activeTab']);
        $this->assertSame('7', $this->rendered['data']['heroImageId']);
        $this->assertSame([], $this->rendered['data']['events']);

        $this->controller()->edit($this->request('GET', query: ['tab' => 'bogus']), new Response(), ['id' => $production->getId()]);
        $this->assertSame('details', $this->rendered['data']['activeTab']);
    }

    public function testStoreRejectsEmptyBody(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());

        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to create production. Please check your input.', $this->rendered['data']['message']);
    }

    public function testStoreCreatesProductionWithPeopleSponsorsAndImages(): void
    {
        $ada = $this->person('Ada', 'Lovelace');
        $alan = $this->person('Alan', 'Turing');
        $sponsor = $this->sponsors->create(['name' => 'Acme']);
        $image = $this->createTestMedia($this->em);

        $result = $this->controller()->store($this->request('POST', [
            'name' => 'Hamlet',
            'seasonId' => (string) $this->season->getId(),
            'creativeIds' => [(string) $ada->getId(), '', '999'],
            'creativeRoles' => ['Director'],
            'performerIds' => [(string) $alan->getId()],
            'performerRoles' => ['Hamlet'],
            'sponsorshipSponsorIds' => [(string) $sponsor->getId(), '', '0'],
            'featuredImageId' => (string) $image->getId(),
            'heroImageId' => (string) $image->getId(),
        ], htmx: true), new Response());

        $production = $this->productions->fetchBySlug('hamlet');
        $this->assertSame('/admin/productions/edit/' . $production->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame(['Director'], $this->roles($production->getCreativeTeam()->toArray()));
        $this->assertSame(['Hamlet'], $this->roles($production->getPerformers()->toArray()));
        $this->assertCount(1, $production->getSponsorships());
        $this->assertSame($image, $production->getFeaturedImage());

        $result = $this->controller()->store(
            $this->request('POST', ['name' => 'Macbeth', 'seasonId' => (string) $this->season->getId()]),
            new Response()
        );
        $this->assertStringStartsWith('/admin/productions/edit/', $result->getHeaderLine('Location'));
    }

    public function testUpdateRejectsEmptyBodyUnknownVenueAndBadDates(): void
    {
        $production = $this->production('Hamlet');
        $base = [
            'productionId' => (string) $production->getId(),
            'seasonId' => (string) $this->season->getId(),
            'name' => 'Hamlet',
            'closing' => '2025-10-31',
        ];

        foreach ([[], $base + ['venueId' => '999'], $base + ['opening' => 'not a date']] as $body) {
            $this->assertSame(400, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());

            $this->rendered = null;
            $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
            $this->assertSame('error', $this->rendered['data']['type']);
        }
    }

    public function testUpdateSavesFieldsAndReconcilesRelations(): void
    {
        $ada = $this->person('Ada', 'Lovelace');
        $alan = $this->person('Alan', 'Turing');
        $grace = $this->person('Grace', 'Hopper');
        $first = $this->sponsors->create(['name' => 'First']);
        $second = $this->sponsors->create(['name' => 'Second']);
        $hamletText = $this->works->create(['title' => 'Hamlet']);
        $prologue = $this->works->create(['title' => 'Prologue']);
        $venue = $this->venue();

        $production = $this->production('Hamlet');
        $id = (string) $production->getId();

        // First save establishes one person per team, a work and a sponsor.
        $this->controller()->update($this->request('POST', [
            'productionId' => $id,
            'seasonId' => (string) $this->season->getId(),
            'name' => 'Hamlet',
            'opening' => '2025-10-01',
            'description' => '',
            'excerpt' => '',
            'promoVideoUrl' => '',
            'ticketPurchaseUrl' => '',
            'closing' => '2025-10-31',
            'works' => (string) $hamletText->getId(),
            'creativeIds' => [(string) $ada->getId()],
            'creativeRoles' => ['Director'],
            'performerIds' => [(string) $alan->getId()],
            'performerRoles' => ['Hamlet'],
            'productionTeamIds' => [(string) $grace->getId()],
            'productionTeamRoles' => ['Stage Manager'],
            'sponsorshipSponsorIds' => (string) $first->getId(),
        ]), new Response());
        $this->em->flush();

        // Second save keeps/renames some, moves others and swaps the sponsor.
        $this->controller()->update($this->request('POST', [
            'productionId' => $id,
            'seasonId' => (string) $this->season->getId(),
            'venueId' => (string) $venue->getId(),
            'name' => 'Hamlet, Prince of Denmark',
            'opening' => '2025-10-02',
            'closing' => '2025-11-01',
            'description' => '{"blocks":[]}',
            'excerpt' => 'To be',
            'promoVideoUrl' => 'https://video.test',
            'ticketPurchaseUrl' => 'https://tickets.test',
            'works' => [(string) $prologue->getId(), (string) $hamletText->getId(), ''],
            'creativeIds' => [(string) $ada->getId(), '', '999'],
            'creativeRoles' => ['Director & Designer'],
            'performerIds' => [(string) $grace->getId(), '999'],
            'performerRoles' => ['Ophelia'],
            'productionTeamIds' => [(string) $alan->getId(), '', '999'],
            'productionTeamRoles' => ['Crew'],
            'sponsorshipSponsorIds' => [(string) $second->getId()],
        ], htmx: true), new Response());
        $this->em->flush();

        $this->assertSame('admin/productions/_saved.html.twig', $this->rendered['template']);
        $this->assertSame('Hamlet, Prince of Denmark', $production->getName());
        $this->assertSame($venue, $production->getVenue());
        $this->assertSame('2025-10-02', $production->getOpening()->format('Y-m-d'));
        $this->assertSame('To be', $production->getExcerpt());
        $positions = [];
        foreach ($production->getProductionWorks() as $productionWork) {
            $positions[$productionWork->getWork()->getTitle()] = $productionWork->getPosition();
        }
        $this->assertSame(['Hamlet' => 1, 'Prologue' => 0], $positions);
        $this->assertSame(['Director & Designer'], $this->roles($production->getCreativeTeam()->toArray()));
        $this->assertSame(['Ophelia'], $this->roles($production->getPerformers()->toArray()));
        $this->assertSame(['Crew'], $this->roles($production->getProductionTeam()->toArray()));
        $this->assertSame(
            [$second->getId()],
            array_map(fn($s) => $s->getSponsor()->getId(), $production->getSponsorships()->getValues())
        );

        $result = $this->controller()->update($this->request('POST', [
            'productionId' => $id,
            'seasonId' => (string) $this->season->getId(),
            'name' => 'Hamlet',
            'opening' => '2025-10-01',
            'closing' => '2025-10-31',
            'description' => '',
            'excerpt' => '',
            'promoVideoUrl' => '',
            'ticketPurchaseUrl' => '',
            'works' => '',
        ]), new Response());
        $this->assertSame('/admin/productions', $result->getHeaderLine('Location'));
        $this->assertCount(0, $production->getWorks());
    }

    public function testUpdateWithOnlyRequiredFieldsClearsOptionalOnes(): void
    {
        $production = $this->productions->create([
            'name' => 'Hamlet',
            'seasonId' => $this->season->getId(),
            'opening' => '2025-10-01',
            'closing' => '2025-10-31',
            'description' => 'Old',
            'ticketPurchaseUrl' => 'https://tickets.test',
        ]);

        $result = $this->controller()->update($this->request('POST', [
            'productionId' => (string) $production->getId(),
            'seasonId' => (string) $this->season->getId(),
            'name' => 'Hamlet',
        ]), new Response());

        $this->assertSame('/admin/productions', $result->getHeaderLine('Location'));
        $this->assertNull($production->getOpening());
        $this->assertNull($production->getClosing());
        $this->assertSame('', $production->getDescription());
        $this->assertSame('', $production->getTicketPurchaseUrl());
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->production('A');
        $b = $this->production('B');

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/productions/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['productions']);

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/productions', $result->getHeaderLine('Location'));
    }

    public function testLoadValidatorMetadataAddsConstraints(): void
    {
        $metadata = new ClassMetadata(Production::class);

        ProductionController::loadValidatorMetadata($metadata);

        $this->assertSame(['name', 'opening', 'closing'], $metadata->getConstrainedProperties());
    }

    /**
     * @param ProductionPerson[] $people
     * @return array<int, string|null>
     */
    private function roles(array $people): array
    {
        return array_values(array_map(fn(ProductionPerson $p) => $p->getRole(), $people));
    }

    private function production(string $name): Production
    {
        return $this->productions->create(['name' => $name, 'seasonId' => $this->season->getId()]);
    }

    private function person(string $first, string $last): Person
    {
        return $this->people->create(['firstName' => $first, 'lastName' => $last]);
    }

    private function venue(): Venue
    {
        return $this->venues->create([
            'name' => 'Main Stage',
            'address' => '1 Stage Rd',
            'city' => 'Testville',
            'state' => 'TS',
            'postcode' => '00000',
        ]);
    }

    private function controller(): ProductionController
    {
        return new ProductionController(
            $this->productions,
            $this->em,
            $this->recordingTwig(),
            new ProductionFormOptionsService(
                $this->seasons,
                $this->people,
                $this->works,
                $this->sponsors,
                $this->venues,
                new EventRepository($this->em),
            ),
            $this->meta,
        );
    }
}
