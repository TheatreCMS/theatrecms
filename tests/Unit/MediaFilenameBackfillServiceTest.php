<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\MediaVariant;
use TheatreCMS\Services\ImageVariantGenerator;
use TheatreCMS\Services\MediaFilenameBackfillService;
use TheatreCMS\Services\MediaUploadService;

/**
 * @coversDefaultClass \TheatreCMS\Services\MediaFilenameBackfillService
 */
#[AllowMockObjectsWithoutExpectations]
class MediaFilenameBackfillServiceTest extends TestCase
{
    private string $uploadsDir;
    private MediaUploadService $mediaUploadService;
    private EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em;
    private ImageVariantGenerator&\PHPUnit\Framework\MockObject\MockObject $generator;
    private MediaFilenameBackfillService $backfillService;

    protected function setUp(): void
    {
        $publicRoot = __DIR__ . '/.theatrecms-filename-backfill-test-' . uniqid();
        $this->uploadsDir = $publicRoot . '/uploads';
        mkdir($this->uploadsDir, 0755, true);

        $this->mediaUploadService = new MediaUploadService($publicRoot);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->generator = $this->createMock(ImageVariantGenerator::class);

        $this->backfillService = new MediaFilenameBackfillService(
            $this->em,
            $this->mediaUploadService,
            $this->generator
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadsDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->uploadsDir);
        rmdir(dirname($this->uploadsDir));
    }

    private function mockAllMedia(array $media): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn($media);
        $this->em->method('getRepository')->willReturn($repository);
    }

    /**
     * Media::$id is only populated by Doctrine on hydration/persistence; set
     * it directly so fixtures constructed with "new Media(...)" behave like
     * rows actually loaded from the database (findAll() always returns
     * hydrated rows in real usage).
     */
    private function withId(Media $media, int $id): Media
    {
        $property = new \ReflectionProperty(Media::class, 'id');
        $property->setValue($media, $id);

        return $media;
    }

    public function testSkipsRowsWithNoOriginalFilename(): void
    {
        $media = new Media('/uploads/abc123.jpg', 'abc123.jpg', Media::TYPE_IMAGE);
        $this->mockAllMedia([$media]);

        $this->generator->expects($this->never())->method('deleteFiles');
        $this->generator->expects($this->never())->method('generate');

        $changes = $this->backfillService->renameToSeoSlugs();

        $this->assertSame([], $changes);
    }

    public function testSkipsRowsWhoseFileIsAlreadyMissingFromDisk(): void
    {
        $media = new Media('/uploads/abc123.jpg', 'abc123.jpg', Media::TYPE_IMAGE);
        $media->setOriginalFilename('poster.jpg');
        $this->mockAllMedia([$media]);

        $changes = $this->backfillService->renameToSeoSlugs();

        $this->assertSame([], $changes);
    }

    public function testSkipsRowsAlreadyMatchingTheTargetFilename(): void
    {
        file_put_contents($this->uploadsDir . '/poster.jpg', 'fake-bytes');

        $media = new Media('/uploads/poster.jpg', 'poster.jpg', Media::TYPE_IMAGE);
        $media->setOriginalFilename('poster.jpg');
        $this->mockAllMedia([$media]);

        $this->generator->expects($this->never())->method('generate');

        $changes = $this->backfillService->renameToSeoSlugs();

        $this->assertSame([], $changes);
    }

    public function testDryRunReportsTheMappingWithoutRenamingOrRegeneratingVariants(): void
    {
        file_put_contents($this->uploadsDir . '/abc123.jpg', 'fake-bytes');

        $media = $this->withId(new Media('/uploads/abc123.jpg', 'abc123.jpg', Media::TYPE_IMAGE), 1);
        $media->setOriginalFilename('My Poster.jpg');
        $this->mockAllMedia([$media]);

        $this->generator->expects($this->never())->method('deleteFiles');
        $this->generator->expects($this->never())->method('generate');
        $this->em->expects($this->never())->method('flush');

        $changes = $this->backfillService->renameToSeoSlugs(true);

        $this->assertSame([[
            'id' => 1,
            'from' => '/uploads/abc123.jpg',
            'to' => '/uploads/my-poster.jpg',
        ]], $changes);
        $this->assertFileExists($this->uploadsDir . '/abc123.jpg');
    }

    public function testRealRunRenamesAndRegeneratesVariantsForImages(): void
    {
        file_put_contents($this->uploadsDir . '/abc123.jpg', 'fake-bytes');

        $media = $this->withId(new Media('/uploads/abc123.jpg', 'abc123.jpg', Media::TYPE_IMAGE), 1);
        $media->setOriginalFilename('My Poster.jpg');
        $this->mockAllMedia([$media]);

        $this->generator->expects($this->once())->method('deleteFiles')->with($media);
        $this->generator->expects($this->once())->method('generate')->with($media);
        $this->em->expects($this->once())->method('persist')->with($media);
        $this->em->expects($this->once())->method('flush');

        $changes = $this->backfillService->renameToSeoSlugs();

        $this->assertSame([[
            'id' => 1,
            'from' => '/uploads/abc123.jpg',
            'to' => '/uploads/my-poster.jpg',
        ]], $changes);
        $this->assertFileExists($this->uploadsDir . '/my-poster.jpg');
        $this->assertFileDoesNotExist($this->uploadsDir . '/abc123.jpg');
        $this->assertSame('/uploads/my-poster.jpg', $media->getUrl());
        $this->assertSame('my-poster.jpg', $media->getFilename());
    }

    public function testRealRunDoesNotTouchVariantsForNonImageMedia(): void
    {
        file_put_contents($this->uploadsDir . '/abc123.pdf', 'fake-bytes');

        $media = $this->withId(new Media('/uploads/abc123.pdf', 'abc123.pdf', Media::TYPE_PDF), 1);
        $media->setOriginalFilename('Program.pdf');
        $this->mockAllMedia([$media]);

        $this->generator->expects($this->never())->method('deleteFiles');
        $this->generator->expects($this->never())->method('generate');

        $changes = $this->backfillService->renameToSeoSlugs();

        $this->assertSame([[
            'id' => 1,
            'from' => '/uploads/abc123.pdf',
            'to' => '/uploads/program.pdf',
        ]], $changes);
    }

    public function testFailedRenameRetainsSourceRowFileAndVariantsAndReportsNothing(): void
    {
        file_put_contents($this->uploadsDir . '/abc123.jpg', 'source-bytes');
        file_put_contents($this->uploadsDir . '/abc123-thumbnail.jpg', 'variant-bytes');

        $media = $this->withId(new Media('/uploads/abc123.jpg', 'abc123.jpg', Media::TYPE_IMAGE), 1);
        $media->setOriginalFilename('My Poster.jpg');
        $media->addVariant(new MediaVariant(
            $media,
            'thumbnail',
            '/uploads/abc123-thumbnail.jpg',
            150,
            150
        ));
        $this->mockAllMedia([$media]);

        $uploadService = $this->getMockBuilder(MediaUploadService::class)
            ->setConstructorArgs([dirname($this->uploadsDir)])
            ->onlyMethods(['renameTo'])
            ->getMock();
        $uploadService->expects($this->once())
            ->method('renameTo')
            ->with('/uploads/abc123.jpg', 'my-poster.jpg')
            ->willReturn(null);

        $service = new MediaFilenameBackfillService($this->em, $uploadService, $this->generator);

        $this->generator->expects($this->never())->method('deleteFiles');
        $this->generator->expects($this->never())->method('generate');
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->assertSame([], $service->renameToSeoSlugs());
        $this->assertSame('/uploads/abc123.jpg', $media->getUrl());
        $this->assertSame('abc123.jpg', $media->getFilename());
        $this->assertFileExists($this->uploadsDir . '/abc123.jpg');
        $this->assertFileExists($this->uploadsDir . '/abc123-thumbnail.jpg');
        $this->assertCount(1, $media->getVariants());
    }
}
