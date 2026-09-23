<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\TermController;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Controllers\TermController
 */
#[AllowMockObjectsWithoutExpectations]
class TermControllerTest extends TestCase
{
    private EntityManager $em;
    private TermRepository $terms;
    private TermRelationshipRepository $relationships;
    private TaxonomyRegistry $registry;

    /** @var array{template: string, data: array<string, mixed>}|null */
    private ?array $rendered = null;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../src/Models'], true);
        $config->enableNativeLazyObjects(true);
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->registry = new TaxonomyRegistry();
        $this->registry->register('genre', ['work'], [
            'label' => 'Genres',
            'singular_label' => 'Genre',
            'capability' => Capability::MANAGE_PEOPLE,
        ]);
        $this->registry->register('post_category', ['post'], ['capability' => Capability::EDIT_POSTS]);

        $this->terms = new TermRepository($this->em, $this->registry);
        $this->relationships = new TermRelationshipRepository($this->em, $this->registry);
    }

    public function testIndexReturns404ForUnknownTaxonomy(): void
    {
        $result = $this->controller()->index($this->request('GET'), new Response(), ['taxonomy' => 'nope']);

        $this->assertSame(404, $result->getStatusCode());
    }

    public function testIndexReturns403WithoutTaxonomyCapability(): void
    {
        $controller = $this->controller([Capability::EDIT_POSTS]);

        $result = $controller->index($this->request('GET'), new Response(), ['taxonomy' => 'genre']);

        $this->assertSame(403, $result->getStatusCode());
        $this->assertNull($this->rendered);
    }

    public function testIndexListsOnlyThisTaxonomysTermsWithCounts(): void
    {
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $this->terms->create(['taxonomy' => 'post_category', 'name' => 'News']);
        $this->relationships->setTerms('work', 7, 'genre', [$comedy->getId()]);

        $this->controller()->index($this->request('GET'), new Response(), ['taxonomy' => 'genre']);

        $this->assertSame('admin/taxonomies/index.html.twig', $this->rendered['template']);
        $this->assertSame(['Comedy'], array_map(fn($t) => $t->getName(), $this->rendered['data']['terms']));
        $this->assertSame([$comedy->getId() => 1], $this->rendered['data']['counts']);
        $this->assertSame('/admin/taxonomies/genre', $this->rendered['data']['basePath']);
    }

    public function testStoreCreatesTermAndRendersRefreshedListForHtmx(): void
    {
        $request = $this->request('POST', ['name' => 'Farce', 'slug' => '', 'description' => ''], htmx: true);

        $this->controller()->store($request, new Response(), ['taxonomy' => 'genre']);

        $created = $this->terms->fetchBySlugInTaxonomy('genre', 'farce');
        $this->assertNotNull($created);
        $this->assertSame('', $created->getDescription());
        $this->assertSame('admin/taxonomies/_created.html.twig', $this->rendered['template']);
        $this->assertSame('Genre "Farce" added.', $this->rendered['data']['message']);
    }

    public function testStoreWithBlankNameRetargetsErrorToForm(): void
    {
        $request = $this->request('POST', ['name' => '  '], htmx: true);

        $result = $this->controller()->store($request, new Response(), ['taxonomy' => 'genre']);

        $this->assertSame('#term-form-errors', $result->getHeaderLine('HX-Retarget'));
        $this->assertSame([], $this->terms->fetchByTaxonomy('genre'));
    }

    public function testUpdateRejectsTermFromAnotherTaxonomy(): void
    {
        $news = $this->terms->create(['taxonomy' => 'post_category', 'name' => 'News']);
        $request = $this->request('POST', ['name' => 'Hijacked']);

        $result = $this->controller()->update($request, new Response(), ['taxonomy' => 'genre', 'id' => $news->getId()]);

        $this->assertSame(404, $result->getStatusCode());
        $this->assertSame('News', $news->getName());
    }

    public function testUpdateRenamesTermAndRedirects(): void
    {
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $request = $this->request('POST', ['name' => 'Comedies', 'slug' => '', 'description' => 'Laughs']);

        $result = $this->controller()->update($request, new Response(), ['taxonomy' => 'genre', 'id' => $comedy->getId()]);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/admin/taxonomies/genre', $result->getHeaderLine('Location'));
        $this->assertSame('Comedies', $comedy->getName());
        $this->assertSame('comedy', $comedy->getSlug());
        $this->assertSame('Laughs', $comedy->getDescription());
    }

    public function testDestroyDeletesTermAndRendersListForHtmx(): void
    {
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $this->relationships->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $this->controller()->destroy(
            $this->request('DELETE', htmx: true),
            new Response(),
            ['taxonomy' => 'genre', 'id' => $comedy->getId()]
        );

        $this->assertSame([], $this->terms->fetchByTaxonomy('genre'));
        $this->assertSame([], $this->relationships->termsFor('work', 1));
        $this->assertSame('admin/taxonomies/_list.html.twig', $this->rendered['template']);
    }

    /**
     * @param string[] $capabilities capabilities the current user holds
     */
    private function controller(array $capabilities = [Capability::MANAGE_PEOPLE, Capability::EDIT_POSTS]): TermController
    {
        $twig = $this->createMock(Twig::class);
        $twig->method('render')->willReturnCallback(function ($response, string $template, array $data = []) {
            $this->rendered = ['template' => $template, 'data' => $data];
            return $response;
        });
        $twig->method('fetch')->willReturn('<div>alert</div>');

        $authorization = $this->createMock(AuthorizationService::class);
        $authorization->method('can')->willReturnCallback(
            static fn(string $capability): bool => in_array($capability, $capabilities, true)
        );

        return new TermController($this->terms, $twig, $this->relationships, $this->registry, $authorization);
    }

    /**
     * @param array<string, string> $body
     */
    private function request(string $method, array $body = [], bool $htmx = false): \Psr\Http\Message\ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, '/admin/taxonomies/genre')
            ->withParsedBody($body);

        return $htmx ? $request->withHeader('HX-Request', 'true') : $request;
    }
}
