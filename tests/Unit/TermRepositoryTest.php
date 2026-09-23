<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\TermRepository
 */
class TermRepositoryTest extends TestCase
{
    private EntityManager $em;
    private TermRepository $repository;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work']);
        $registry->register('post_category', ['post']);

        $this->repository = new TermRepository($this->em, $registry);
    }

    public function testCreateGeneratesSlugFromName(): void
    {
        $term = $this->repository->create(['taxonomy' => 'genre', 'name' => 'Musical Comedy']);

        $this->assertSame('genre', $term->getTaxonomy());
        $this->assertSame('musical-comedy', $term->getSlug());
    }

    public function testSameSlugIsAllowedInDifferentTaxonomies(): void
    {
        $genre = $this->repository->create(['taxonomy' => 'genre', 'name' => 'News']);
        $category = $this->repository->create(['taxonomy' => 'post_category', 'name' => 'News']);

        $this->assertSame('news', $genre->getSlug());
        $this->assertSame('news', $category->getSlug());
    }

    public function testDuplicateSlugWithinTaxonomyIsSuffixed(): void
    {
        $this->repository->create(['taxonomy' => 'genre', 'name' => 'Drama']);
        $second = $this->repository->create(['taxonomy' => 'genre', 'name' => 'Drama']);

        $this->assertSame('drama-1', $second->getSlug());
    }

    public function testCreateRejectsUnknownTaxonomy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->create(['taxonomy' => 'tag', 'name' => 'Anything']);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->create(['taxonomy' => 'genre', 'name' => '  ']);
    }

    public function testFetchByTaxonomyReturnsOnlyThatTaxonomyAlphabetically(): void
    {
        $this->repository->create(['taxonomy' => 'genre', 'name' => 'Tragedy']);
        $this->repository->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $this->repository->create(['taxonomy' => 'post_category', 'name' => 'Announcements']);

        $names = array_map(fn($term) => $term->getName(), $this->repository->fetchByTaxonomy('genre'));

        $this->assertSame(['Comedy', 'Tragedy'], $names);
    }

    public function testFetchPageFiltersByTaxonomyCriterion(): void
    {
        $this->repository->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $this->repository->create(['taxonomy' => 'post_category', 'name' => 'Press Releases']);

        $page = $this->repository->fetchPage(criteria: ['taxonomy' => 'post_category']);

        $this->assertSame(1, $page['total']);
        $this->assertSame('Press Releases', $page['items'][0]->getName());
    }
}
