<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Repositories\ImageRepository;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\ImageRepository
 */
class ImageRepositoryTest extends TestCase
{
    private EntityManager $em;
    private ImageRepository $repository;

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

        $this->repository = new ImageRepository($this->em);
    }

    public function testCreatePersistsAllProvidedFields(): void
    {
        $image = $this->repository->create([
            'url' => '/uploads/abc123.jpg',
            'filename' => 'abc123.jpg',
            'originalFilename' => 'my-photo.jpg',
            'mimeType' => 'image/jpeg',
            'sizeBytes' => 1024,
            'altText' => 'A photo',
        ]);

        $this->assertSame('/uploads/abc123.jpg', $image->getUrl());
        $this->assertSame('abc123.jpg', $image->getFilename());
        $this->assertSame('my-photo.jpg', $image->getOriginalFilename());
        $this->assertSame('image/jpeg', $image->getMimeType());
        $this->assertSame(1024, $image->getSizeBytes());
        $this->assertSame('A photo', $image->getAltText());
        $this->assertNotNull($image->getId());
    }

    public function testCreateThrowsWhenUrlIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->create(['filename' => 'foo.jpg']);
    }

    public function testCreateDefaultsFilenameFromUrl(): void
    {
        $image = $this->repository->create(['url' => '/uploads/xyz.png']);

        $this->assertSame('xyz.png', $image->getFilename());
    }

    public function testFindByUrlReturnsMatchAndNullOtherwise(): void
    {
        $this->repository->create(['url' => '/uploads/found.jpg', 'filename' => 'found.jpg']);

        $this->assertNotNull($this->repository->findByUrl('/uploads/found.jpg'));
        $this->assertNull($this->repository->findByUrl('/uploads/missing.jpg'));
    }

    public function testFetchPageSearchesByFilename(): void
    {
        $this->repository->create(['url' => '/uploads/sunset-photo.jpg', 'filename' => 'sunset-photo.jpg']);
        $this->repository->create(['url' => '/uploads/logo.png', 'filename' => 'logo.png']);

        $result = $this->repository->fetchPage(1, 25, 'sunset');

        $this->assertCount(1, $result['items']);
        $this->assertSame('sunset-photo.jpg', $result['items'][0]->getFilename());
    }

    public function testFetchPageSortsByFilenameAscending(): void
    {
        $this->repository->create(['url' => '/uploads/b.jpg', 'filename' => 'b.jpg']);
        $this->repository->create(['url' => '/uploads/a.jpg', 'filename' => 'a.jpg']);

        $result = $this->repository->fetchPage(1, 25, '', 'filename', 'asc');

        $this->assertSame('a.jpg', $result['items'][0]->getFilename());
        $this->assertSame('b.jpg', $result['items'][1]->getFilename());
    }

    public function testFetchPageDefaultsToNewestFirst(): void
    {
        $older = $this->repository->create(['url' => '/uploads/older.jpg', 'filename' => 'older.jpg']);
        $older->setUploadedAt(new \DateTimeImmutable('-1 day'));

        $newer = $this->repository->create(['url' => '/uploads/newer.jpg', 'filename' => 'newer.jpg']);
        $newer->setUploadedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        $result = $this->repository->fetchPage(1, 25);

        $this->assertSame($newer->getId(), $result['items'][0]->getId());
        $this->assertSame($older->getId(), $result['items'][1]->getId());
    }
}
