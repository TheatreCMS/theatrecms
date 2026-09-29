<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\Controllers\PersonController;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

#[AllowMockObjectsWithoutExpectations]
class PersonControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private PersonRepository $people;
    private ContentMetaRepository $meta;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->meta = new ContentMetaRepository($this->em);
        $this->people = new PersonRepository($this->em, $this->meta);
    }

    public function testIndexFiltersBySearchAndSwitchesTemplateForHtmx(): void
    {
        $this->people->create(['firstName' => 'Ada', 'lastName' => 'Lovelace']);
        $this->people->create(['firstName' => 'Alan', 'lastName' => 'Turing']);

        $this->controller()->index($this->request('GET', query: ['q' => 'Tur']), new Response());
        $this->assertSame('admin/people/index.html.twig', $this->rendered['template']);
        $this->assertSame(['Turing'], array_map(fn($p) => $p->getLastName(), $this->rendered['data']['people']));

        $this->controller()->index($this->request('GET', htmx: true), new Response());
        $this->assertSame('admin/people/_list.html.twig', $this->rendered['template']);
        $this->assertCount(2, $this->rendered['data']['people']);
    }

    public function testCreateEditAndQuickCreateRender(): void
    {
        $person = $this->people->create(['firstName' => 'Ada', 'lastName' => 'Lovelace']);

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/people/create.html.twig', $this->rendered['template']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $person->getId()]);
        $this->assertSame('admin/people/edit.html.twig', $this->rendered['template']);
        $this->assertSame($person, $this->rendered['data']['person']);
        $this->assertNull($this->rendered['data']['heroImageId']);

        $this->controller()->quickCreate($this->request('GET'), new Response());
        $this->assertSame('admin/people/_quick_create_modal.html.twig', $this->rendered['template']);
    }

    public function testStoreRejectsEmptyBody(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());

        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('error', $this->rendered['data']['type']);
    }

    public function testStoreCreatesPersonAndRedirectsToEdit(): void
    {
        $image = $this->createTestMedia($this->em);

        $result = $this->controller()->store($this->request('POST', [
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'featuredImageId' => (string) $image->getId(),
            'heroImageId' => (string) $image->getId(),
        ], htmx: true), new Response());

        $person = $this->people->fetchBySlug('ada-lovelace');
        $this->assertNotNull($person);
        $this->assertSame('/admin/people/edit/' . $person->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame($image, $person->getFeaturedImage());
        $this->assertSame((string) $image->getId(), $this->meta->get('person', $person->getId(), 'hero_image_id'));

        $result = $this->controller()->store($this->request('POST', ['firstName' => 'Alan', 'lastName' => 'Turing']), new Response());
        $this->assertStringStartsWith('/admin/people/edit/', $result->getHeaderLine('Location'));
    }

    public function testStoreAndUpdateAcceptAMissingNameField(): void
    {
        $this->controller()->store($this->request('POST', ['firstName' => 'Cher']), new Response());
        $person = $this->people->fetchBySlug('cher');
        $this->assertSame('', $person->getLastName());

        $this->controller()->update(
            $this->request('POST', ['personId' => (string) $person->getId(), 'lastName' => 'Bono']),
            new Response()
        );
        $this->assertSame('', $person->getFirstName());
        $this->assertSame('Bono', $person->getLastName());
    }

    public function testQuickStoreCreatesPersonAndTriggersEvent(): void
    {
        $result = $this->controller()->quickStore(
            $this->request('POST', ['firstName' => 'Ada', 'lastName' => 'Lovelace']),
            new Response()
        );

        $trigger = json_decode($result->getHeaderLine('HX-Trigger'), true);
        $this->assertSame('person', $trigger['entityCreated']['type']);
        $this->assertSame('Ada Lovelace', $trigger['entityCreated']['name']);
        $this->assertSame('Ada Lovelace was added.', $this->rendered['data']['message']);
    }

    public function testQuickStoreRejectsEmptyBody(): void
    {
        $result = $this->controller()->quickStore($this->request('POST'), new Response());

        $this->assertSame('', $result->getHeaderLine('HX-Trigger'));
        $this->assertSame('error', $this->rendered['data']['type']);
    }

    public function testUpdateValidatesBodyAndPerson(): void
    {
        $this->assertSame(400, $this->controller()->update($this->request('POST'), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to save person. Please check your input.', $this->rendered['data']['message']);

        $body = ['personId' => '999', 'firstName' => 'X'];
        $this->assertSame(404, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
        $this->assertSame('Person not found.', $this->rendered['data']['message']);
    }

    public function testUpdateSavesFields(): void
    {
        $person = $this->people->create(['firstName' => 'Ada', 'lastName' => 'Lovelace']);

        $this->controller()->update($this->request('POST', [
            'personId' => (string) $person->getId(),
            'firstName' => 'Augusta Ada',
            'lastName' => 'King',
            'biography' => '{"blocks":[]}',
            'headshotUrl' => 'https://example.com/ada.jpg',
        ], htmx: true), new Response());

        $this->assertSame('Person saved successfully.', $this->rendered['data']['message']);
        $this->assertSame('Augusta Ada', $person->getFirstName());
        $this->assertSame('King', $person->getLastName());
        $this->assertSame('https://example.com/ada.jpg', $person->getHeadshotUrl());

        $result = $this->controller()->update(
            $this->request('POST', ['personId' => (string) $person->getId(), 'firstName' => 'Ada', 'lastName' => 'King']),
            new Response()
        );
        $this->assertSame('/admin/people', $result->getHeaderLine('Location'));
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->people->create(['firstName' => 'Ada', 'lastName' => 'Lovelace']);
        $b = $this->people->create(['firstName' => 'Alan', 'lastName' => 'Turing']);

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/people/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['people']);

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/people', $result->getHeaderLine('Location'));
    }

    public function testRemoveImagesUsesPeopleRoutePrefix(): void
    {
        $person = $this->people->create(['firstName' => 'Ada', 'lastName' => 'Lovelace']);

        $result = $this->controller()->removeFeaturedImage($this->request('DELETE'), new Response(), ['id' => $person->getId()]);
        $this->assertSame('/admin/people/edit/' . $person->getId(), $result->getHeaderLine('Location'));

        $this->controller()->removeHeroImage($this->request('DELETE', htmx: true), new Response(), ['id' => $person->getId()]);
        $this->assertSame('/admin/people/' . $person->getId() . '/hero-image', $this->rendered['data']['deleteUrl']);
    }

    private function controller(): PersonController
    {
        return new PersonController($this->people, $this->recordingTwig(), $this->em, $this->meta);
    }
}
