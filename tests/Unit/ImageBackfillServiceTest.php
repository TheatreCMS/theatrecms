<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Post;
use TheatreCMS\Repositories\ImageRepository;
use TheatreCMS\Services\ImageBackfillService;

/**
 * @coversDefaultClass \TheatreCMS\Services\ImageBackfillService
 *
 * Simulates the real rollout scenario: the `featured_image_url` column still
 * physically exists on posts/productions/seasons (added back manually here,
 * since current entity mapping no longer declares it) while the app has
 * already moved to `featured_image_id`. See migrations/20260903_*.sql and
 * documentation/DEPLOYMENT.md for the actual rollout ordering this mirrors.
 */
class ImageBackfillServiceTest extends TestCase
{
    private EntityManager $em;
    private ImageRepository $imageRepository;
    private ImageBackfillService $backfillService;
    private string $uploadsDir;

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

        // Simulate the post-migration, pre-drop rollout window: the legacy
        // column still exists on disk even though it's no longer mapped.
        foreach (['posts', 'productions', 'seasons'] as $table) {
            $this->em->getConnection()->executeStatement(
                "ALTER TABLE {$table} ADD COLUMN featured_image_url VARCHAR(255) DEFAULT NULL"
            );
        }

        $this->imageRepository = new ImageRepository($this->em);

        $this->uploadsDir = sys_get_temp_dir() . '/theatrecms-backfill-test-' . uniqid();
        mkdir($this->uploadsDir);

        $this->backfillService = new ImageBackfillService(
            $this->em->getConnection(),
            $this->imageRepository,
            $this->uploadsDir
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadsDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->uploadsDir)) {
            rmdir($this->uploadsDir);
        }
    }

    public function testScanUploadsCreatesAnImageRowPerFile(): void
    {
        file_put_contents($this->uploadsDir . '/photo1.jpg', 'fake-bytes');
        file_put_contents($this->uploadsDir . '/photo2.png', 'fake-bytes');

        $created = $this->backfillService->scanUploads();

        $this->assertSame(2, $created);
        $this->assertNotNull($this->imageRepository->findByUrl('/uploads/photo1.jpg'));
        $this->assertNotNull($this->imageRepository->findByUrl('/uploads/photo2.png'));
    }

    public function testScanUploadsIsIdempotent(): void
    {
        file_put_contents($this->uploadsDir . '/photo1.jpg', 'fake-bytes');

        $this->assertSame(1, $this->backfillService->scanUploads());
        $this->assertSame(0, $this->backfillService->scanUploads());
    }

    public function testScanUploadsDryRunCreatesNothing(): void
    {
        file_put_contents($this->uploadsDir . '/photo1.jpg', 'fake-bytes');

        $created = $this->backfillService->scanUploads(true);

        $this->assertSame(1, $created);
        $this->assertNull($this->imageRepository->findByUrl('/uploads/photo1.jpg'));
    }

    public function testRepointAllMatchesLegacyUrlToImageAndSetsForeignKey(): void
    {
        $post = new Post('A Post', ContentStatus::DRAFT, 'body');
        $post->setSlug('a-post');
        $this->em->persist($post);
        $this->em->flush();

        $this->em->getConnection()->update('posts', ['featured_image_url' => '/uploads/legacy.jpg'], ['id' => $post->getId()]);

        $this->imageRepository->create(['url' => '/uploads/legacy.jpg', 'filename' => 'legacy.jpg']);

        $counts = $this->backfillService->repointAll();

        $this->assertSame(1, $counts['posts']);

        $row = $this->em->getConnection()->fetchAssociative('SELECT featured_image_id FROM posts WHERE id = ?', [$post->getId()]);
        $image = $this->imageRepository->findByUrl('/uploads/legacy.jpg');

        $this->assertSame($image->getId(), (int) $row['featured_image_id']);
    }

    public function testRepointAllSkipsRowsAlreadyRepointed(): void
    {
        $post = new Post('A Post', ContentStatus::DRAFT, 'body');
        $post->setSlug('a-post');
        $this->em->persist($post);
        $this->em->flush();

        $image = $this->imageRepository->create(['url' => '/uploads/legacy.jpg', 'filename' => 'legacy.jpg']);
        $this->em->getConnection()->update('posts', [
            'featured_image_url' => '/uploads/legacy.jpg',
            'featured_image_id' => $image->getId(),
        ], ['id' => $post->getId()]);

        $counts = $this->backfillService->repointAll();

        $this->assertSame(0, $counts['posts']);
    }
}
