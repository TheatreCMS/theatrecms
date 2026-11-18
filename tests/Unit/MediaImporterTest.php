<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\MediaRepository;
use TheatreCMS\Services\ImageVariantGenerator;
use TheatreCMS\Services\MediaImporter;
use TheatreCMS\Services\MediaUploadService;

#[AllowMockObjectsWithoutExpectations]
class MediaImporterTest extends TestCase
{
    private string $publicRoot;

    protected function setUp(): void
    {
        $this->publicRoot = __DIR__ . '/.theatrecms-media-importer-test-' . uniqid();
        mkdir($this->publicRoot . '/uploads', 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->publicRoot . '/uploads/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->publicRoot . '/uploads');
        rmdir($this->publicRoot);
    }

    public function testImportRemovesTheStoredFileWhenTheLibraryRecordCannotBeCreated(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->method('create')->willThrowException(new \RuntimeException('database is down'));

        $generator = $this->createMock(ImageVariantGenerator::class);
        $generator->expects($this->never())->method('generate');

        $importer = new MediaImporter(new MediaUploadService($this->publicRoot), $repository, $generator);
        $file = new UploadedFile(Utils::streamFor('bytes'), 5, UPLOAD_ERR_OK, 'poster.png', 'image/png');

        try {
            $importer->import($file, Media::TYPE_IMAGE);
            $this->fail('Expected the repository failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('database is down', $e->getMessage());
        }

        $this->assertSame([], glob($this->publicRoot . '/uploads/*'));
    }
}
