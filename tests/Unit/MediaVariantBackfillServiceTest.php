<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\MediaVariant;
use TheatreCMS\Services\ImageVariantGenerator;
use TheatreCMS\Services\MediaVariantBackfillService;
use TheatreCMS\Theme\ImageSizeRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Services\MediaVariantBackfillService
 */
class MediaVariantBackfillServiceTest extends TestCase
{
    private EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em;
    private ImageVariantGenerator&\PHPUnit\Framework\MockObject\MockObject $generator;
    private ImageSizeRegistry $registry;
    private MediaVariantBackfillService $backfillService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->generator = $this->createMock(ImageVariantGenerator::class);

        $this->registry = new ImageSizeRegistry();
        $this->registry->register('thumb', 150, 150, true);
        $this->registry->register('medium', 600, 600, false);

        $this->backfillService = new MediaVariantBackfillService($this->em, $this->generator, $this->registry);
    }

    private function mockImageRepository(array $images): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->with(['mediaType' => Media::TYPE_IMAGE])->willReturn($images);
        $this->em->method('getRepository')->willReturn($repository);
    }

    public function testRegenerateOnlyGeneratesSizesMissingForEachImage(): void
    {
        $withThumb = new Media('/uploads/a.jpg', 'a.jpg', Media::TYPE_IMAGE);
        $withThumb->addVariant(new MediaVariant($withThumb, 'thumb', '/uploads/a-thumb.jpg', 150, 150));

        $withNothing = new Media('/uploads/b.jpg', 'b.jpg', Media::TYPE_IMAGE);

        $this->mockImageRepository([$withThumb, $withNothing]);

        $this->generator->expects($this->exactly(2))->method('generate')->willReturnCallback(
            function (Media $media, ?array $onlySizes) use ($withThumb, $withNothing) {
                if ($media === $withThumb) {
                    $this->assertSame(['medium'], $onlySizes);
                } elseif ($media === $withNothing) {
                    $this->assertSame(['thumb', 'medium'], $onlySizes);
                } else {
                    $this->fail('Unexpected media passed to generate().');
                }
            }
        );

        $counts = $this->backfillService->regenerate();

        $this->assertSame(['thumb' => 1, 'medium' => 2], $counts);
    }

    public function testRegenerateSkipsImagesThatAlreadyHaveEveryRegisteredSize(): void
    {
        $complete = new Media('/uploads/a.jpg', 'a.jpg', Media::TYPE_IMAGE);
        $complete->addVariant(new MediaVariant($complete, 'thumb', '/uploads/a-thumb.jpg', 150, 150));
        $complete->addVariant(new MediaVariant($complete, 'medium', '/uploads/a-medium.jpg', 600, 450));

        $this->mockImageRepository([$complete]);

        $this->generator->expects($this->never())->method('generate');

        $counts = $this->backfillService->regenerate();

        $this->assertSame(['thumb' => 0, 'medium' => 0], $counts);
    }

    public function testForceRegeneratesEveryRegisteredSizeRegardlessOfExistingVariants(): void
    {
        $complete = new Media('/uploads/a.jpg', 'a.jpg', Media::TYPE_IMAGE);
        $complete->addVariant(new MediaVariant($complete, 'thumb', '/uploads/a-thumb.jpg', 150, 150));
        $complete->addVariant(new MediaVariant($complete, 'medium', '/uploads/a-medium.jpg', 600, 450));

        $this->mockImageRepository([$complete]);

        $this->generator->expects($this->once())->method('generate')->with($complete, ['thumb', 'medium']);

        $counts = $this->backfillService->regenerate(false, true);

        $this->assertSame(['thumb' => 1, 'medium' => 1], $counts);
    }

    public function testOnlySizeRestrictsRegenerationToOneRegisteredSize(): void
    {
        $media = new Media('/uploads/a.jpg', 'a.jpg', Media::TYPE_IMAGE);
        $this->mockImageRepository([$media]);

        $this->generator->expects($this->once())->method('generate')->with($media, ['thumb']);

        $counts = $this->backfillService->regenerate(false, false, 'thumb');

        $this->assertSame(['thumb' => 1], $counts);
    }

    public function testDryRunReportsCountsWithoutCallingTheGenerator(): void
    {
        $media = new Media('/uploads/a.jpg', 'a.jpg', Media::TYPE_IMAGE);
        $this->mockImageRepository([$media]);

        $this->generator->expects($this->never())->method('generate');

        $counts = $this->backfillService->regenerate(true);

        $this->assertSame(['thumb' => 1, 'medium' => 1], $counts);
    }
}
