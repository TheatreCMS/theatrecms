<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\TermRelationshipRepository
 */
class TermRelationshipRepositoryTest extends TestCase
{
    private EntityManager $em;
    private TermRepository $terms;
    private TermRelationshipRepository $repository;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work']);
        $registry->register('audience', ['work']);
        $registry->register('post_category', ['post'], ['multiple' => false]);

        $this->terms = new TermRepository($this->em, $registry);
        $this->repository = new TermRelationshipRepository($this->em, $registry);
    }

    public function testSetTermsAssignsInGivenOrder(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $musical = $this->term('genre', 'Musical');

        $this->repository->setTerms('work', 1, 'genre', [$musical->getId(), $comedy->getId()]);

        $this->assertSame(['Musical', 'Comedy'], $this->names($this->repository->termsFor('work', 1, 'genre')));
    }

    public function testSetTermsReplacesPreviousAssignmentsForThatTaxonomyOnly(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $drama = $this->term('genre', 'Drama');
        $family = $this->term('audience', 'Family');

        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);
        $this->repository->setTerms('work', 1, 'audience', [$family->getId()]);
        $this->repository->setTerms('work', 1, 'genre', [$drama->getId()]);

        $this->assertSame(['Drama'], $this->names($this->repository->termsFor('work', 1, 'genre')));
        $this->assertSame(['Family'], $this->names($this->repository->termsFor('work', 1, 'audience')));
    }

    public function testSetTermsWithEmptyListClearsTaxonomy(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $this->repository->setTerms('work', 1, 'genre', []);

        $this->assertSame([], $this->repository->termsFor('work', 1, 'genre'));
    }

    public function testSameIdOnDifferentContentTypesDoesNotCollide(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $news = $this->term('post_category', 'News');

        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);
        $this->repository->setTerms('post', 1, 'post_category', [$news->getId()]);

        $this->assertSame(['Comedy'], $this->names($this->repository->termsFor('work', 1)));
        $this->assertSame(['News'], $this->names($this->repository->termsFor('post', 1)));
    }

    public function testSetTermsRejectsTaxonomyNotApplicableToContentType(): void
    {
        $comedy = $this->term('genre', 'Comedy');

        $this->expectException(InvalidArgumentException::class);

        $this->repository->setTerms('post', 1, 'genre', [$comedy->getId()]);
    }

    public function testSetTermsEnforcesSingleTermTaxonomy(): void
    {
        $news = $this->term('post_category', 'News');
        $press = $this->term('post_category', 'Press Releases');

        $this->expectException(InvalidArgumentException::class);

        $this->repository->setTerms('post', 1, 'post_category', [$news->getId(), $press->getId()]);
    }

    public function testSetTermsRejectsTermFromAnotherTaxonomy(): void
    {
        $family = $this->term('audience', 'Family');

        $this->expectException(InvalidArgumentException::class);

        $this->repository->setTerms('work', 1, 'genre', [$family->getId()]);
    }

    public function testContentIdsForReturnsTaggedItems(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $this->repository->setTerms('work', 3, 'genre', [$comedy->getId()]);
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $this->assertSame([1, 3], $this->repository->contentIdsFor($comedy, 'work'));
    }

    public function testDeleteAllForContentRemovesOnlyThatItem(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);
        $this->repository->setTerms('work', 2, 'genre', [$comedy->getId()]);

        $this->repository->deleteAllForContent('work', 1);

        $this->assertSame([2], $this->repository->contentIdsFor($comedy, 'work'));
    }

    public function testDeletingTermCascadesToRelationships(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);
        $this->em->clear();

        $this->terms->delete($this->terms->fetch($comedy->getId()));
        $this->em->clear();

        $this->assertSame([], $this->repository->termsFor('work', 1));
    }

    public function testDeletingTermWithRelationshipsLoadedInSameRequest(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $drama = $this->term('genre', 'Drama');
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId(), $drama->getId()]);

        $this->terms->delete($comedy);
        $this->assertSame(['Drama'], $this->names($this->repository->termsFor('work', 1)));

        // a later flush must not trip over stale relationships still pointing at the removed term
        $this->terms->delete($drama);
        $this->assertSame([], $this->repository->termsFor('work', 1));
    }

    public function testDeleteAllForContentThenDeletingTermInSameRequest(): void
    {
        $comedy = $this->term('genre', 'Comedy');
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $drama = $this->term('genre', 'Drama');
        $this->repository->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $this->repository->deleteAllForContent('work', 1);
        $this->terms->delete($comedy);
        $this->terms->delete($drama);

        $this->assertSame([], $this->terms->fetchByTaxonomy('genre'));
    }

    private function term(string $taxonomy, string $name): Term
    {
        return $this->terms->create(['taxonomy' => $taxonomy, 'name' => $name]);
    }

    /**
     * @param Term[] $terms
     * @return string[]
     */
    private function names(array $terms): array
    {
        return array_map(fn(Term $term) => $term->getName(), $terms);
    }
}
