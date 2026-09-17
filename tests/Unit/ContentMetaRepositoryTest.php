<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Repositories\ContentMetaRepository;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\ContentMetaRepository
 */
class ContentMetaRepositoryTest extends TestCase
{
    private EntityManager $em;
    private ContentMetaRepository $repository;

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

        $this->repository = new ContentMetaRepository($this->em);
    }

    public function testSetOnNewTripleCreatesRowAndGetReturnsValue(): void
    {
        $this->repository->set('production', 1, 'hero_image_id', '42');

        $this->assertSame('42', $this->repository->get('production', 1, 'hero_image_id'));
    }

    public function testSetOnExistingTripleUpdatesInPlace(): void
    {
        $meta = $this->repository->set('production', 1, 'hero_image_id', '42');
        $originalId = $meta->getId();

        $updated = $this->repository->set('production', 1, 'hero_image_id', '99');

        $this->assertSame($originalId, $updated->getId());
        $this->assertSame('99', $this->repository->get('production', 1, 'hero_image_id'));
    }

    public function testGetForMissingTripleReturnsNull(): void
    {
        $this->assertNull($this->repository->get('production', 1, 'hero_image_id'));
    }

    public function testDifferentContentTypesWithSameIdAndKeyDoNotCollide(): void
    {
        $this->repository->set('production', 1, 'hero_image_id', '42');
        $this->repository->set('work', 1, 'hero_image_id', '99');

        $this->assertSame('42', $this->repository->get('production', 1, 'hero_image_id'));
        $this->assertSame('99', $this->repository->get('work', 1, 'hero_image_id'));
    }

    public function testGetAllForContentReturnsOnlyThatItemsKeys(): void
    {
        $this->repository->set('production', 1, 'hero_image_id', '42');
        $this->repository->set('production', 1, 'subtitle', 'A New Hope');
        $this->repository->set('production', 2, 'hero_image_id', '7');
        $this->repository->set('work', 1, 'hero_image_id', '99');

        $result = $this->repository->getAllForContent('production', 1);

        $this->assertSame(['hero_image_id' => '42', 'subtitle' => 'A New Hope'], $result);
    }

    public function testDeleteRemovesOnlyTargetedTriple(): void
    {
        $this->repository->set('production', 1, 'hero_image_id', '42');
        $this->repository->set('production', 1, 'subtitle', 'A New Hope');

        $this->repository->delete('production', 1, 'hero_image_id');

        $this->assertNull($this->repository->get('production', 1, 'hero_image_id'));
        $this->assertSame('A New Hope', $this->repository->get('production', 1, 'subtitle'));
    }

    public function testDeleteAllForContentRemovesEveryKeyForThatItem(): void
    {
        $this->repository->set('production', 1, 'hero_image_id', '42');
        $this->repository->set('production', 1, 'subtitle', 'A New Hope');
        $this->repository->set('production', 2, 'hero_image_id', '7');

        $this->repository->deleteAllForContent('production', 1);

        $this->assertNull($this->repository->get('production', 1, 'hero_image_id'));
        $this->assertNull($this->repository->get('production', 1, 'subtitle'));
        $this->assertSame('7', $this->repository->get('production', 2, 'hero_image_id'));
    }
}
