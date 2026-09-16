<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Media;
use TheatreCMS\Services\MediaTypeClassifier;

/**
 * @coversDefaultClass \TheatreCMS\Services\MediaTypeClassifier
 */
class MediaTypeClassifierTest extends TestCase
{
    public function testClassifiesByExactMimeType(): void
    {
        $this->assertSame(Media::TYPE_PDF, MediaTypeClassifier::classify('application/pdf', 'pdf'));
    }

    public function testClassifiesByMimePrefix(): void
    {
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify('image/jpeg', 'jpg'));
        $this->assertSame(Media::TYPE_AUDIO, MediaTypeClassifier::classify('audio/mpeg', 'mp3'));
        $this->assertSame(Media::TYPE_VIDEO, MediaTypeClassifier::classify('video/quicktime', 'mov'));
    }

    public function testFallsBackToExtensionWhenMimeTypeIsMissingOrGeneric(): void
    {
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify(null, 'png'));
        $this->assertSame(Media::TYPE_PDF, MediaTypeClassifier::classify('application/octet-stream', 'pdf'));
        $this->assertSame(Media::TYPE_AUDIO, MediaTypeClassifier::classify('application/octet-stream', 'wav'));
        $this->assertSame(Media::TYPE_VIDEO, MediaTypeClassifier::classify('application/octet-stream', 'webm'));
    }

    public function testReturnsOtherForUnsupportedCombinations(): void
    {
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify('application/x-php', 'php'));
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify('image/jpeg', 'php'));
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify(null, 'exe'));
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify(null, ''));
    }

    public function testIsSupportedMatchesClassifyResult(): void
    {
        $this->assertTrue(MediaTypeClassifier::isSupported('image/png', 'png'));
        $this->assertFalse(MediaTypeClassifier::isSupported('application/zip', 'zip'));
    }

    public function testAllowedExtensionsCoversEveryCategory(): void
    {
        $extensions = MediaTypeClassifier::allowedExtensions();

        foreach (['jpg', 'png', 'pdf', 'mp3', 'mp4'] as $expected) {
            $this->assertContains($expected, $extensions);
        }
    }

    public function testExtensionMatchingIsCaseInsensitiveAndIgnoresLeadingDot(): void
    {
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify('IMAGE/JPEG', '.JPG'));
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify(null, '.JPG'));
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify(null, 'JPG'));
    }

    public function testMimeClassificationRequiresASupportedExtension(): void
    {
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify('image/jpeg', 'PHP'));
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify('application/pdf', 'txt'));
        $this->assertSame(Media::TYPE_OTHER, MediaTypeClassifier::classify('audio/mpeg', ''));
    }

    public function testMismatchedMimeTypeFallsBackToTheAllowedExtensionCategory(): void
    {
        $this->assertSame(Media::TYPE_PDF, MediaTypeClassifier::classify('image/jpeg', 'pdf'));
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify('application/pdf', 'jpg'));
        $this->assertSame(Media::TYPE_VIDEO, MediaTypeClassifier::classify('audio/mpeg', 'mov'));
    }

    public function testClassifiesEveryValidMediaCategoryWithNormalizedExtensions(): void
    {
        $this->assertSame(Media::TYPE_IMAGE, MediaTypeClassifier::classify('image/jpeg', '.JPEG'));
        $this->assertSame(Media::TYPE_PDF, MediaTypeClassifier::classify('application/pdf', '.PDF'));
        $this->assertSame(Media::TYPE_AUDIO, MediaTypeClassifier::classify('audio/mp4', '.M4A'));
        $this->assertSame(Media::TYPE_VIDEO, MediaTypeClassifier::classify('video/mp4', '.MP4'));
    }
}
