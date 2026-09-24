<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Traits\AssignsTaxonomyTerms;

/**
 * @coversDefaultClass \TheatreCMS\Traits\AssignsTaxonomyTerms
 */
class AssignsTaxonomyTermsTest extends TestCase
{
    private TermRepository $terms;
    private TermRelationshipRepository $relationships;
    private object $subject;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../../src/Models'], true);
        $config->enableNativeLazyObjects(true);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work']);

        $this->terms = new TermRepository($em, $registry);
        $this->relationships = new TermRelationshipRepository($em, $registry);

        $this->subject = new class ($this->relationships) {
            use AssignsTaxonomyTerms {
                applyTerms as public;
            }

            public function __construct(private readonly TermRelationshipRepository $relationships)
            {
            }

            protected function termRelationships(): TermRelationshipRepository
            {
                return $this->relationships;
            }
        };
    }

    public function testAssignsSubmittedTermsIgnoringThePlaceholderValue(): void
    {
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);

        $this->subject->applyTerms('work', 1, ['terms' => ['genre' => ['', (string) $comedy->getId()]]]);

        $this->assertSame(['Comedy'], $this->names());
    }

    public function testPlaceholderOnlyClearsTheTaxonomy(): void
    {
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $this->relationships->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $this->subject->applyTerms('work', 1, ['terms' => ['genre' => ['']]]);

        $this->assertSame([], $this->names());
    }

    public function testMissingTermsKeyLeavesAssignmentsUntouched(): void
    {
        $comedy = $this->terms->create(['taxonomy' => 'genre', 'name' => 'Comedy']);
        $this->relationships->setTerms('work', 1, 'genre', [$comedy->getId()]);

        $this->subject->applyTerms('work', 1, ['title' => 'Hamlet']);

        $this->assertSame(['Comedy'], $this->names());
    }

    /**
     * @return string[]
     */
    private function names(): array
    {
        return array_map(fn($term) => $term->getName(), $this->relationships->termsFor('work', 1, 'genre'));
    }
}
