<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\Controllers\WorksController;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Work;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

#[AllowMockObjectsWithoutExpectations]
class WorksControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private WorkRepository $works;
    private PersonRepository $people;
    private TermRepository $terms;
    private TermRelationshipRepository $relationships;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work'], ['multiple' => false]);
        $this->works = new WorkRepository($this->em);
        $this->people = new PersonRepository($this->em, new ContentMetaRepository($this->em));
        $this->terms = new TermRepository($this->em, $registry);
        $this->relationships = new TermRelationshipRepository($this->em, $registry);
    }

    public function testIndexSearchesAndSortsByAuthor(): void
    {
        $shaw = $this->person('George', 'Shaw');
        $ibsen = $this->person('Henrik', 'Ibsen');
        $this->works->create(['title' => 'Pygmalion', 'creators' => [$shaw->getId()]]);
        $this->works->create(['title' => 'Peer Gynt', 'creators' => [['personId' => $ibsen->getId()]]]);
        $this->works->create(['title' => 'Hamlet']);

        $this->controller()->index($this->request('GET', query: ['q' => 'P', 'sort' => 'author']), new Response());
        $this->assertSame('admin/works/index.html.twig', $this->rendered['template']);
        $this->assertSame(['Peer Gynt', 'Pygmalion'], $this->titles($this->rendered['data']['works']));

        $this->controller()->index(
            $this->request('GET', htmx: true, query: ['sort' => 'title', 'direction' => 'desc']),
            new Response()
        );
        $this->assertSame('admin/works/_list.html.twig', $this->rendered['template']);
        $this->assertSame(['Pygmalion', 'Peer Gynt', 'Hamlet'], $this->titles($this->rendered['data']['works']));
    }

    public function testCreateQuickCreateAndEditRender(): void
    {
        $shaw = $this->person('George', 'Shaw');
        $work = $this->works->create(['title' => 'Pygmalion', 'creators' => [['id' => $shaw->getId(), 'role' => 'Playwright']]]);

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/works/create.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['people']);

        $this->controller()->quickCreate($this->request('GET'), new Response());
        $this->assertSame('admin/works/_quick_create_modal.html.twig', $this->rendered['template']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $work->getId()]);
        $this->assertSame($work, $this->rendered['data']['work']);
        $this->assertSame(
            [['personId' => $shaw->getId(), 'personName' => $shaw->getName(), 'role' => 'Playwright']],
            $this->rendered['data']['creatorEntries']
        );
    }

    public function testStoreRejectsEmptyBody(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());

        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to create work. Please check your input.', $this->rendered['data']['message']);
    }

    public function testStoreCreatesWorkWithCreatorRowsAndTerms(): void
    {
        $shaw = $this->person('George', 'Shaw');
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);

        $result = $this->controller()->store($this->request('POST', [
            'title' => 'Pygmalion',
            'creatorIds' => [(string) $shaw->getId(), ''],
            'creatorRoles' => ['Playwright', 'ignored'],
            'terms' => ['genre' => ['', (string) $comedy->getId()]],
        ], htmx: true), new Response());

        $work = $this->works->fetchBySlug('pygmalion');
        $this->assertSame('/admin/works/edit/' . $work->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame('Playwright', $work->getWorkCreators()->first()->role());
        $this->assertSame(['Comedy'], array_map(fn($t) => $t->getName(), $this->relationships->termsFor('work', $work->getId())));
    }

    public function testStoreStillRedirectsWhenTermsAreInvalid(): void
    {
        $shaw = $this->person('George', 'Shaw');
        $a = $this->terms->create(['taxonomy' => 'genre', 'name' => 'A']);
        $b = $this->terms->create(['taxonomy' => 'genre', 'name' => 'B']);

        $result = $this->controller()->store($this->request('POST', [
            'title' => 'Pygmalion',
            'creators' => [(string) $shaw->getId(), '0'],
            'creators_roles' => [$shaw->getId() => ' Author '],
            'terms' => ['genre' => [(string) $a->getId(), (string) $b->getId()]],
        ]), new Response());

        $work = $this->works->fetchBySlug('pygmalion');
        $this->assertSame('/admin/works/edit/' . $work->getId(), $result->getHeaderLine('Location'));
        $this->assertSame('Author', $work->getWorkCreators()->first()->role());
        $this->assertSame([], $this->relationships->termsFor('work', $work->getId()));
    }

    public function testQuickStore(): void
    {
        $shaw = $this->person('George', 'Shaw');

        $result = $this->controller()->quickStore($this->request('POST', ['title' => ' ']), new Response());
        $this->assertSame('A title is required to create a work.', $this->rendered['data']['message']);
        $this->assertSame('', $result->getHeaderLine('HX-Trigger'));

        $result = $this->controller()->quickStore(
            $this->request('POST', ['title' => 'Pygmalion', 'creatorId' => (string) $shaw->getId(), 'creatorRole' => 'Playwright']),
            new Response()
        );
        $trigger = json_decode($result->getHeaderLine('HX-Trigger'), true);
        $this->assertSame('Pygmalion', $trigger['entityCreated']['name']);
        $this->assertSame('"Pygmalion" was added.', $this->rendered['data']['message']);
        $this->assertCount(1, $this->works->fetchBySlug('pygmalion')->getWorkCreators());
    }

    public function testUpdateValidatesIdAndWork(): void
    {
        $this->assertSame(400, $this->controller()->update($this->request('POST', ['title' => 'x']), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', ['title' => 'x'], htmx: true), new Response());
        $this->assertSame('Unable to save work. Please check your input.', $this->rendered['data']['message']);

        $this->assertSame(404, $this->controller()->update($this->request('POST', ['id' => '99']), new Response())->getStatusCode());
        $this->controller()->update($this->request('POST', ['id' => '99'], htmx: true), new Response());
        $this->assertSame('Work not found.', $this->rendered['data']['message']);
    }

    public function testUpdateSavesFieldsCreatorsAndTerms(): void
    {
        $shaw = $this->person('George', 'Shaw');
        $ibsen = $this->person('Henrik', 'Ibsen');
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $work = $this->works->create(['title' => 'Pygmalion', 'creators' => [$shaw->getId()]]);

        $this->controller()->update($this->request('POST', [
            'id' => (string) $work->getId(),
            'title' => 'Pygmalion (revised)',
            'synopsis' => 'A flower girl.',
            'creatorIds' => [(string) $ibsen->getId()],
            'creatorRoles' => ['Adapter'],
            'terms' => ['genre' => [(string) $comedy->getId()]],
        ], htmx: true), new Response());

        $this->assertSame('Work saved successfully.', $this->rendered['data']['message']);
        $this->assertSame('Pygmalion (revised)', $work->getTitle());
        $this->assertSame('A flower girl.', $work->getSynopsis());
        $this->assertSame([$ibsen->getId()], $work->getWorkCreators()->map(fn($wc) => $wc->person()->getId())->getValues());
        $this->assertCount(1, $this->relationships->termsFor('work', $work->getId()));

        $result = $this->controller()->update($this->request('POST', ['id' => (string) $work->getId()]), new Response());
        $this->assertSame('/admin/works', $result->getHeaderLine('Location'));
    }

    public function testUpdateReportsInvalidTerms(): void
    {
        $work = $this->works->create(['title' => 'Pygmalion']);
        $a = $this->terms->create(['taxonomy' => 'genre', 'name' => 'A']);
        $b = $this->terms->create(['taxonomy' => 'genre', 'name' => 'B']);
        $body = ['id' => (string) $work->getId(), 'terms' => ['genre' => [(string) $a->getId(), (string) $b->getId()]]];

        $this->assertSame(400, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());

        $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
        $this->assertStringStartsWith('Work saved, but its terms could not be updated: ', $this->rendered['data']['message']);
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->works->create(['title' => 'A']);
        $b = $this->works->create(['title' => 'B']);

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/works/_list.html.twig', $this->rendered['template']);
        $this->assertSame(['B'], $this->titles($this->rendered['data']['works']));

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/works', $result->getHeaderLine('Location'));
    }

    /**
     * @param Work[] $works
     * @return string[]
     */
    private function titles(array $works): array
    {
        return array_map(fn(Work $w) => $w->getTitle(), $works);
    }

    private function person(string $first, string $last): Person
    {
        return $this->people->create(['firstName' => $first, 'lastName' => $last]);
    }

    private function controller(): WorksController
    {
        return new WorksController($this->works, $this->recordingTwig(), $this->people, $this->relationships);
    }
}
