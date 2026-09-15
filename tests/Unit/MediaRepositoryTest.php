<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\MediaRepository
 */
class MediaRepositoryTest extends TestCase
{
    private EntityManager $em;
    private MediaRepository $repository;

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

        $this->repository = new MediaRepository($this->em);
    }

    public function testCreatePersistsAllProvidedFields(): void
    {
        $media = $this->repository->create([
            'url' => '/uploads/abc123.jpg',
            'filename' => 'abc123.jpg',
            'originalFilename' => 'my-photo.jpg',
            'mimeType' => 'image/jpeg',
            'sizeBytes' => 1024,
            'altText' => 'A photo',
            'mediaType' => Media::TYPE_IMAGE,
        ]);

        $this->assertSame('/uploads/abc123.jpg', $media->getUrl());
        $this->assertSame('abc123.jpg', $media->getFilename());
        $this->assertSame('my-photo.jpg', $media->getOriginalFilename());
        $this->assertSame('image/jpeg', $media->getMimeType());
        $this->assertSame(1024, $media->getSizeBytes());
        $this->assertSame('A photo', $media->getAltText());
        $this->assertSame(Media::TYPE_IMAGE, $media->getMediaType());
        $this->assertNotNull($media->getId());
    }

    public function testCreateThrowsWhenUrlIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->create(['filename' => 'foo.jpg', 'mediaType' => Media::TYPE_IMAGE]);
    }

    public function testCreateThrowsWhenMediaTypeIsMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->create(['url' => '/uploads/foo.jpg']);
    }

    public function testCreateThrowsWhenMediaTypeIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->create(['url' => '/uploads/foo.jpg', 'mediaType' => 'bogus']);
    }

    public function testCreateDefaultsFilenameFromUrl(): void
    {
        $media = $this->repository->create(['url' => '/uploads/xyz.png', 'mediaType' => Media::TYPE_IMAGE]);

        $this->assertSame('xyz.png', $media->getFilename());
    }

    public function testCreateSkipsDimensionResolutionForNonImageTypes(): void
    {
        $pdf = $this->repository->create(['url' => '/uploads/doc.pdf', 'mediaType' => Media::TYPE_PDF]);
        $audio = $this->repository->create(['url' => '/uploads/song.mp3', 'mediaType' => Media::TYPE_AUDIO]);
        $video = $this->repository->create(['url' => '/uploads/clip.mp4', 'mediaType' => Media::TYPE_VIDEO]);

        foreach ([$pdf, $audio, $video] as $media) {
            $this->assertNull($media->getWidth());
            $this->assertNull($media->getHeight());
        }
    }

    public function testFindByUrlReturnsMatchAndNullOtherwise(): void
    {
        $this->repository->create(['url' => '/uploads/found.jpg', 'filename' => 'found.jpg', 'mediaType' => Media::TYPE_IMAGE]);

        $this->assertNotNull($this->repository->findByUrl('/uploads/found.jpg'));
        $this->assertNull($this->repository->findByUrl('/uploads/missing.jpg'));
    }

    public function testFetchPageSearchesByFilename(): void
    {
        $this->repository->create(['url' => '/uploads/sunset-photo.jpg', 'filename' => 'sunset-photo.jpg', 'mediaType' => Media::TYPE_IMAGE]);
        $this->repository->create(['url' => '/uploads/logo.png', 'filename' => 'logo.png', 'mediaType' => Media::TYPE_IMAGE]);

        $result = $this->repository->fetchPage(1, 25, 'sunset');

        $this->assertCount(1, $result['items']);
        $this->assertSame('sunset-photo.jpg', $result['items'][0]->getFilename());
    }

    public function testFetchPageSortsByFilenameAscending(): void
    {
        $this->repository->create(['url' => '/uploads/b.jpg', 'filename' => 'b.jpg', 'mediaType' => Media::TYPE_IMAGE]);
        $this->repository->create(['url' => '/uploads/a.jpg', 'filename' => 'a.jpg', 'mediaType' => Media::TYPE_IMAGE]);

        $result = $this->repository->fetchPage(1, 25, '', 'filename', 'asc');

        $this->assertSame('a.jpg', $result['items'][0]->getFilename());
        $this->assertSame('b.jpg', $result['items'][1]->getFilename());
    }

    public function testFetchPageDefaultsToNewestFirst(): void
    {
        $older = $this->repository->create(['url' => '/uploads/older.jpg', 'filename' => 'older.jpg', 'mediaType' => Media::TYPE_IMAGE]);
        $older->setUploadedAt(new \DateTimeImmutable('-1 day'));

        $newer = $this->repository->create(['url' => '/uploads/newer.jpg', 'filename' => 'newer.jpg', 'mediaType' => Media::TYPE_IMAGE]);
        $newer->setUploadedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        $result = $this->repository->fetchPage(1, 25);

        $this->assertSame($newer->getId(), $result['items'][0]->getId());
        $this->assertSame($older->getId(), $result['items'][1]->getId());
    }

    public function testFetchPageFiltersByTypeCriterion(): void
    {
        $this->repository->create(['url' => '/uploads/a.jpg', 'filename' => 'a.jpg', 'mediaType' => Media::TYPE_IMAGE]);
        $this->repository->create(['url' => '/uploads/b.pdf', 'filename' => 'b.pdf', 'mediaType' => Media::TYPE_PDF]);

        $result = $this->repository->fetchPage(1, 25, '', '', 'asc', ['type' => Media::TYPE_PDF]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('b.pdf', $result['items'][0]->getFilename());
    }
}
