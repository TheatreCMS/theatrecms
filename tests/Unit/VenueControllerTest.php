<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * Covers the CRUD actions; featured-image handling lives in VenueControllerFeaturedImageTest.
 */
#[AllowMockObjectsWithoutExpectations]
class VenueControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private const ADDRESS = [
        'address' => '1 Stage Rd',
        'city' => 'Testville',
        'state' => 'TS',
        'postcode' => '00000',
    ];

    private EntityManager $em;
    private VenueRepository $venues;
    private ContentMetaRepository $meta;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->meta = new ContentMetaRepository($this->em);
        $this->venues = new VenueRepository($this->em, $this->meta);
    }

    public function testIndexCreateEditAndQuickCreateRender(): void
    {
        $venue = $this->venue('Main Stage');

        $this->controller()->index($this->request('GET'), new Response());
        $this->assertSame('admin/venues/index.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['venues']);

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/venues/create.html.twig', $this->rendered['template']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $venue->getId()]);
        $this->assertSame($venue, $this->rendered['data']['venue']);
        $this->assertNull($this->rendered['data']['heroImageId']);

        $this->controller()->quickCreate($this->request('GET'), new Response());
        $this->assertSame('admin/venues/_quick_create_modal.html.twig', $this->rendered['template']);
    }

    public function testStoreValidatesAndCreates(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());
        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to create venue. Please check your input.', $this->rendered['data']['message']);

        $image = $this->createTestMedia($this->em);
        $result = $this->controller()->store(
            $this->request('POST', ['name' => 'Main Stage', 'heroImageId' => (string) $image->getId()] + self::ADDRESS, htmx: true),
            new Response()
        );
        $venue = $this->venues->fetchBySlug('main-stage');
        $this->assertSame('/admin/venues/edit/' . $venue->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame((string) $image->getId(), $this->meta->get('venue', $venue->getId(), 'hero_image_id'));

        $result = $this->controller()->store($this->request('POST', ['name' => 'Studio'] + self::ADDRESS), new Response());
        $this->assertStringStartsWith('/admin/venues/edit/', $result->getHeaderLine('Location'));
    }

    public function testQuickStore(): void
    {
        $this->controller()->quickStore($this->request('POST'), new Response());
        $this->assertSame('error', $this->rendered['data']['type']);

        $result = $this->controller()->quickStore($this->request('POST', ['name' => 'Studio'] + self::ADDRESS), new Response());
        $trigger = json_decode($result->getHeaderLine('HX-Trigger'), true);
        $this->assertSame('venue', $trigger['entityCreated']['type']);
        $this->assertSame('Studio was added.', $this->rendered['data']['message']);
    }

    public function testUpdateValidatesBodyAndVenue(): void
    {
        $this->assertSame(400, $this->controller()->update($this->request('POST'), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to save venue. Please check your input.', $this->rendered['data']['message']);

        $body = ['venueId' => '99', 'name' => 'x'];
        $this->assertSame(404, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
        $this->assertSame('Venue not found.', $this->rendered['data']['message']);
    }

    public function testUpdateSavesAllFields(): void
    {
        $venue = $this->venue('Main Stage');

        $this->controller()->update($this->request('POST', [
            'venueId' => (string) $venue->getId(),
            'name' => 'Grand Hall',
            'address' => '2 Hall St',
            'city' => 'Hallville',
            'state' => 'HV',
            'postcode' => '11111',
            'capacity' => '450',
            'description' => 'Big',
            'accessibilityInfo' => 'Step-free',
            'websiteUrl' => 'https://hall.test',
            'mapUrl' => 'https://maps.test',
        ], htmx: true), new Response());

        $this->assertSame('Venue saved successfully.', $this->rendered['data']['message']);
        $this->assertSame('Grand Hall', $venue->getName());
        $this->assertSame('Hallville', $venue->getCity());
        $this->assertSame(450, $venue->getCapacity());
        $this->assertSame('Step-free', $venue->getAccessibilityInfo());

        $result = $this->controller()->update(
            $this->request('POST', ['venueId' => (string) $venue->getId(), 'name' => 'Grand Hall', 'capacity' => 'lots'] + self::ADDRESS),
            new Response()
        );
        $this->assertSame('/admin/venues', $result->getHeaderLine('Location'));
        $this->assertNull($venue->getCapacity());
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->venue('A');
        $b = $this->venue('B');

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/venues/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['venues']);

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/venues', $result->getHeaderLine('Location'));
    }

    private function venue(string $name): Venue
    {
        return $this->venues->create(['name' => $name] + self::ADDRESS);
    }

    private function controller(): VenueController
    {
        return new VenueController($this->venues, $this->em, $this->recordingTwig(), $this->meta);
    }
}
