<?php

namespace Clubdeuce\TheatreCMS\Tests\Integration;

use Clubdeuce\TheatreCMS\Models\Venue;
use Clubdeuce\TheatreCMS\Repositories\VenueRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Clubdeuce\TheatreCMS\Repositories\VenueRepository
 */
class TestVenueRepository extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        // Skip if PDO SQLite driver isn't available in this environment (CI or local dev may not have it)
        if (!in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('PDO SQLite driver is not available; skipping integration test.');
        }

        $paths = [__DIR__ . '/../../src/Models'];
        $isDevMode = true;

        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $connection = DriverManager::getConnection($connectionParams);

        $this->em = new EntityManager($connection, $config);

        // Create schema
        $schemaTool = new SchemaTool($this->em);
        $classes = [$this->em->getClassMetadata(Venue::class)];
        $schemaTool->createSchema($classes);
    }

    public function testCreateAndFetchVenue(): void
    {
        $repo = new VenueRepository($this->em);

        $data = [
            'name' => 'Integration Theatre',
            'address' => '100 Integration Way',
            'city' => 'Testville',
            'state' => 'TS',
            'postcode' => '00000',
            'capacity' => 123,
            'description' => 'Integration test venue',
            'accessibilityInfo' => 'Accessible',
            'websiteUrl' => 'https://example.test',
            'mapUrl' => 'https://maps.example.test'
        ];

        $venue = $repo->create($data);

        // Basic sanity
        $this->assertInstanceOf(Venue::class, $venue);
        $this->assertGreaterThan(0, $venue->getId());

        // Assert properties on the returned entity
        $this->assertEquals($data['name'], $venue->getName());
        $this->assertEquals($data['address'], $venue->getAddress());
        $this->assertEquals($data['city'], $venue->getCity());
        $this->assertEquals($data['state'], $venue->getState());
        $this->assertEquals($data['postcode'], $venue->getPostcode());
        $this->assertEquals($data['capacity'], $venue->getCapacity());
        $this->assertEquals($data['description'], $venue->getDescription());
        $this->assertEquals($data['accessibilityInfo'], $venue->getAccessibilityInfo());
        $this->assertEquals($data['websiteUrl'], $venue->getWebsiteUrl());
        $this->assertEquals($data['mapUrl'], $venue->getMapUrl());

        // Fetch from the EntityManager and assert the stored values
        $fetched = $this->em->getRepository(Venue::class)->find($venue->getId());

        $this->assertNotNull($fetched);
        $this->assertInstanceOf(Venue::class, $fetched);

        $this->assertEquals($data['name'], $fetched->getName());
        $this->assertEquals($data['address'], $fetched->getAddress());
        $this->assertEquals($data['city'], $fetched->getCity());
        $this->assertEquals($data['state'], $fetched->getState());
        $this->assertEquals($data['postcode'], $fetched->getPostcode());
        $this->assertEquals($data['capacity'], $fetched->getCapacity());
        $this->assertEquals($data['description'], $fetched->getDescription());
        $this->assertEquals($data['accessibilityInfo'], $fetched->getAccessibilityInfo());
        $this->assertEquals($data['websiteUrl'], $fetched->getWebsiteUrl());
        $this->assertEquals($data['mapUrl'], $fetched->getMapUrl());
    }

    public function testUpdateVenue(): void
    {
        $repo = new VenueRepository($this->em);

        $venue = $repo->create([
            'name' => 'Update Theatre',
            'address' => '1 Update Road',
            'city' => 'Oldcity',
            'state' => 'OC',
            'postcode' => '11111',
        ]);

        $this->assertEquals('Oldcity', $venue->getCity());

        // Change some fields
        $venue->setCity('Newcity')
              ->setCapacity(999)
              ->setDescription('Updated description');

        // Use BaseRepository::update (inherited)
        $repo->update($venue);

        // Fetch fresh from EM and assert changes
        $fresh = $this->em->getRepository(Venue::class)->find($venue->getId());
        $this->assertEquals('Newcity', $fresh->getCity());
        $this->assertEquals(999, $fresh->getCapacity());
        $this->assertEquals('Updated description', $fresh->getDescription());
    }

    public function testDeleteVenue(): void
    {
        $repo = new VenueRepository($this->em);

        $venue = $repo->create([
            'name' => 'Delete Theatre',
            'address' => '2 Remove Ave',
            'city' => 'Deletecity',
            'state' => 'DC',
            'postcode' => '22222',
        ]);

        $id = $venue->getId();
        $this->assertGreaterThan(0, $id);

        // Delete and assert it no longer exists
        $repo->delete($venue);

        $deleted = $this->em->getRepository(Venue::class)->find($id);
        $this->assertNull($deleted);
    }

    public function testFetchAllVenues(): void
    {
        $repo = new VenueRepository($this->em);

        // Ensure a clean state by relying on in-memory DB per test
        $repo->create([
            'name' => 'First',
            'address' => 'Addr 1',
            'city' => 'C1',
            'state' => 'S1',
            'postcode' => 'P1',
        ]);

        $repo->create([
            'name' => 'Second',
            'address' => 'Addr 2',
            'city' => 'C2',
            'state' => 'S2',
            'postcode' => 'P2',
        ]);

        $all = $repo->fetchAll();

        $this->assertIsArray($all);
        $this->assertGreaterThanOrEqual(2, count($all));
        $this->assertInstanceOf(Venue::class, $all[0]);
    }
}
