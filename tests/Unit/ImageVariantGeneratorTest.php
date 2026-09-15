<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Media;
use TheatreCMS\Models\MediaVariant;
use TheatreCMS\Services\ImageVariantGenerator;
use TheatreCMS\Services\MediaUploadService;
use TheatreCMS\Theme\ImageSizeRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Services\ImageVariantGenerator
 */
class ImageVariantGeneratorTest extends TestCase
{
    private string $uploadsDir;
    private string $sourcePath;
    private ImageVariantGenerator $generator;
    private MediaUploadService&\PHPUnit\Framework\MockObject\MockObject $mediaUploadService;
    private EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em;
    private ImageSizeRegistry $registry;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('The gd extension is not available.');
        }

        $this->uploadsDir = sys_get_temp_dir() . '/theatrecms-variant-test-' . uniqid();
        mkdir($this->uploadsDir);

        // A real 400x300 source image, large enough to exercise both crop
        // and scale-down behavior against the registered test sizes below.
        $this->sourcePath = $this->uploadsDir . '/source.jpg';
        $image = imagecreatetruecolor(400, 300);
        imagejpeg($image, $this->sourcePath);
        imagedestroy($image);

        $this->registry = new ImageSizeRegistry();
        $this->registry->register('square-crop', 100, 100, true);
        $this->registry->register('fit-within', 200, 200, false);

        $this->mediaUploadService = $this->createMock(MediaUploadService::class);
        $this->mediaUploadService->method('resolvePath')->willReturn($this->sourcePath);

        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->generator = new ImageVariantGenerator(
            ImageManager::gd(),
            $this->mediaUploadService,
            $this->em,
            $this->registry
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

    private function makeMedia(): Media
    {
        $media = new Media('/uploads/source.jpg', 'source.jpg', Media::TYPE_IMAGE);

        return $media;
    }

    public function testGenerateCropsAHardCroppedSizeToExactDimensions(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $media = $this->makeMedia();
        $this->generator->generate($media);

        $variantPath = $this->uploadsDir . '/source-square-crop.jpg';
        $this->assertFileExists($variantPath);
        [$width, $height] = getimagesize($variantPath);
        $this->assertSame(100, $width);
        $this->assertSame(100, $height);
    }

    public function testGenerateScalesDownAProportionalSizeWithoutUpscaling(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('persist');
        $this->em->method('flush');

        $media = $this->makeMedia();
        $this->generator->generate($media, ['fit-within']);

        $variantPath = $this->uploadsDir . '/source-fit-within.jpg';
        $this->assertFileExists($variantPath);
        [$width, $height] = getimagesize($variantPath);

        // 400x300 fit within 200x200, preserving the 4:3 aspect ratio.
        $this->assertSame(200, $width);
        $this->assertSame(150, $height);
    }

    public function testGeneratePopulatesTheMediaVariantsCollection(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('persist');
        $this->em->method('flush');

        $media = $this->makeMedia();
        $this->generator->generate($media, ['square-crop']);

        $this->assertSame('/uploads/source-square-crop.jpg', $media->getVariantUrl('square-crop'));
    }

    public function testGenerateIsANoOpForNonImageMedia(): void
    {
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $pdf = new Media('/uploads/doc.pdf', 'doc.pdf', Media::TYPE_PDF);
        $this->generator->generate($pdf);

        $this->assertFileDoesNotExist($this->uploadsDir . '/doc-square-crop.pdf');
    }

    public function testDeleteFilesDelegatesToMediaUploadServicePerVariant(): void
    {
        $media = $this->makeMedia();
        $media->addVariant(new MediaVariant($media, 'square-crop', '/uploads/source-square-crop.jpg', 100, 100));
        $media->addVariant(new MediaVariant($media, 'fit-within', '/uploads/source-fit-within.jpg', 200, 150));

        $this->mediaUploadService->expects($this->exactly(2))->method('delete')->willReturnCallback(
            function (?string $url) {
                $this->assertContains($url, ['/uploads/source-square-crop.jpg', '/uploads/source-fit-within.jpg']);
            }
        );

        $this->generator->deleteFiles($media);
    }
}
