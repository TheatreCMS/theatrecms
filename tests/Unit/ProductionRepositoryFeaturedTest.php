<?php

namespace TheatreCMS\Tests\Unit;

use DateTime;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\ProductionRepository;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\ProductionRepository
 */
class ProductionRepositoryFeaturedTest extends TestCase
{
    private EntityManager $em;
    private ProductionRepository $repository;
    private Season $season;
    private Venue $venue;

    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->repository = new ProductionRepository($this->em);

        $this->season = new Season('2025-2026', '2025-2026 Season');
        $this->season->setStartDate(new DateTime('2025-09-01'));
        $this->season->setEndDate(new DateTime('2026-06-30'));
        $this->venue = new Venue('Main Stage', '1 Stage Rd', 'Testville', 'TS', '00000');
        $this->venue->setSlug('main-stage');
        $this->em->persist($this->season);
        $this->em->persist($this->venue);
        $this->em->flush();
    }

    private function createProduction(string $name, ?string $opening, ?string $closing): Production
    {
        $production = new Production($name, $this->season);
        $production->setVenue($this->venue);
        $production->setSlug(strtolower(str_replace(' ', '-', $name)));
        $production->setOpening($opening ? new DateTime($opening) : null);
        $production->setClosing($closing ? new DateTime($closing) : null);

        $this->em->persist($production);
        $this->em->flush();

        return $production;
    }

    public function testPrefersCurrentlyRunningProductionOverUpcoming(): void
    {
        $this->createProduction('Future Show', '+30 days', '+40 days');
        $running = $this->createProduction('Running Show', '-5 days', '+5 days');

        $featured = $this->repository->findFeatured();

        $this->assertNotNull($featured);
        $this->assertSame($running->getId(), $featured->getId());
    }

    public function testFallsBackToNextUpcomingProductionWhenNoneAreRunning(): void
    {
        $this->createProduction('Far Future Show', '+60 days', '+70 days');
        $soon = $this->createProduction('Soon Show', '+10 days', '+20 days');

        $featured = $this->repository->findFeatured();

        $this->assertNotNull($featured);
        $this->assertSame($soon->getId(), $featured->getId());
    }

    public function testIgnoresProductionsThatHaveAlreadyClosed(): void
    {
        $this->createProduction('Past Show', '-30 days', '-20 days');

        $featured = $this->repository->findFeatured();

        $this->assertNull($featured);
    }

    public function testTreatsOpenEndedRunAsStillRunning(): void
    {
        $openEnded = $this->createProduction('Open Ended Show', '-5 days', null);

        $featured = $this->repository->findFeatured();

        $this->assertNotNull($featured);
        $this->assertSame($openEnded->getId(), $featured->getId());
    }

    public function testReturnsNullWhenNoProductionsExist(): void
    {
        $this->assertNull($this->repository->findFeatured());
    }
}
