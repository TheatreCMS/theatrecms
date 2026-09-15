<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Services\MediaUploadService;

/**
 * @coversDefaultClass \TheatreCMS\Services\MediaUploadService
 */
class MediaUploadServiceTest extends TestCase
{
    private string $publicRoot;
    private string $uploadsDir;
    private MediaUploadService $service;

    protected function setUp(): void
    {
        $this->publicRoot = sys_get_temp_dir() . '/theatrecms-upload-test-' . uniqid();
        $this->uploadsDir = $this->publicRoot . '/uploads';
        mkdir($this->uploadsDir, 0755, true);

        $this->service = new MediaUploadService($this->publicRoot);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadsDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->uploadsDir);
        rmdir($this->publicRoot);
    }

    public function testGenerateUniqueFilenameSlugifiesPunctuationAndSpaces(): void
    {
        $filename = $this->service->generateUniqueFilename('My Great Poster!', 'jpg', $this->uploadsDir);

        $this->assertSame('my-great-poster.jpg', $filename);
    }

    public function testGenerateUniqueFilenameTransliteratesAccentedCharacters(): void
    {
        $filename = $this->service->generateUniqueFilename('Café Menu', 'jpg', $this->uploadsDir);

        $this->assertSame('cafe-menu.jpg', $filename);
    }

    public function testGenerateUniqueFilenameFallsBackToFileWhenNothingSurvivesSlugification(): void
    {
        $filename = $this->service->generateUniqueFilename('★★★', 'jpg', $this->uploadsDir);

        $this->assertSame('file.jpg', $filename);
    }

    public function testGenerateUniqueFilenameAppendsIncrementingSuffixOnCollision(): void
    {
        touch($this->uploadsDir . '/poster.jpg');

        $second = $this->service->generateUniqueFilename('poster', 'jpg', $this->uploadsDir);
        $this->assertSame('poster-1.jpg', $second);

        touch($this->uploadsDir . '/poster-1.jpg');

        $third = $this->service->generateUniqueFilename('poster', 'jpg', $this->uploadsDir);
        $this->assertSame('poster-2.jpg', $third);
    }

    public function testGenerateUniqueFilenameTreatsTheExcludedFilenameAsNotACollision(): void
    {
        touch($this->uploadsDir . '/poster.jpg');

        $filename = $this->service->generateUniqueFilename('poster', 'jpg', $this->uploadsDir, 'poster.jpg');

        $this->assertSame('poster.jpg', $filename);
    }

    public function testGenerateUniqueFilenameDoesNotTouchTheFilesystem(): void
    {
        $this->service->generateUniqueFilename('poster', 'jpg', $this->uploadsDir);

        $this->assertSame([], glob($this->uploadsDir . '/*'));
    }

    public function testRenameToMovesTheFileAndReturnsTheNewUrl(): void
    {
        file_put_contents($this->uploadsDir . '/3ddfb7a0765f10f8c7b6c495.jpg', 'fake-bytes');

        $newUrl = $this->service->renameTo('/uploads/3ddfb7a0765f10f8c7b6c495.jpg', 'poster.jpg');

        $this->assertSame('/uploads/poster.jpg', $newUrl);
        $this->assertFileExists($this->uploadsDir . '/poster.jpg');
        $this->assertFileDoesNotExist($this->uploadsDir . '/3ddfb7a0765f10f8c7b6c495.jpg');
    }

    public function testRenameToReturnsNullForAMissingSourceFile(): void
    {
        $newUrl = $this->service->renameTo('/uploads/does-not-exist.jpg', 'poster.jpg');

        $this->assertNull($newUrl);
    }
}
