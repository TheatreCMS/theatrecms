<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\ContentType\ContentTypeRegistry;

class ContentTypeRegistryTest extends TestCase
{
    private const BUILTIN_CONTENT_TYPES = [
        'seasons',
        'productions',
        'works',
        'people',
        'posts',
    ];

    public function testDefaultPrefixMatchesTypeName(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('seasons', $registry->prefix('seasons'));
    }

    public function testOverridePrefixFromConfig(): void
    {
        $registry = new ContentTypeRegistry(['seasons' => ['urlPrefix' => 'shows']]);

        $this->assertSame('shows', $registry->prefix('seasons'));
    }

    public function testUnknownTypePassesThroughUnchanged(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('widgets', $registry->prefix('widgets'));
    }

    public function testDefaultLabelMatchesCapitalizedTypeName(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('Seasons', $registry->label('seasons'));
    }

    public function testExpandedOverrideSetsIndependentLabel(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['urlPrefix' => 'shows', 'label' => 'Our Shows'],
        ]);

        $this->assertSame('shows', $registry->prefix('seasons'));
        $this->assertSame('Our Shows', $registry->label('seasons'));
    }

    public function testUnknownTypeLabelFallsBackToCapitalizedType(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('Widgets', $registry->label('widgets'));
    }

    public function testArchiveEnabledByDefault(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertTrue($registry->hasArchive('seasons'));
        $this->assertTrue($registry->hasArchive('widgets'));
    }

    public function testArchiveDisabledFromConfig(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['hasArchive' => false],
        ]);

        $this->assertFalse($registry->hasArchive('seasons'));
    }

    public function testArchiveDisabledAlongsideUrlPrefixOverride(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['urlPrefix' => 'shows', 'hasArchive' => false],
        ]);

        $this->assertSame('shows', $registry->prefix('seasons'));
        $this->assertFalse($registry->hasArchive('seasons'));
    }

    public function testStringOverrideThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContentTypeRegistry(['seasons' => 'shows']);
    }

    public function testUnknownOverrideKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('url_prefix');
        new ContentTypeRegistry(['seasons' => ['url_prefix' => 'shows']]);
    }

    public function testHasForBuiltInTypes(): void
    {
        $registry = new ContentTypeRegistry();

        foreach (self::BUILTIN_CONTENT_TYPES as $contentType) {
            $this->assertTrue($registry->has($contentType), "Unknown content type: {$contentType}");
        }
    }

    public function testHasForUnknownTypeReturnsFalse(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertFalse($registry->has('foo'));
    }

    public function testRequireForBuiltInTypes(): void
    {
        $registry = new ContentTypeRegistry();

        foreach (self::BUILTIN_CONTENT_TYPES as $type) {
            $this->assertSame($type, $registry->require($type)->name);
        }
    }

    public function testRequireForUnknownTypeThrowsError(): void
    {
        $registry = new ContentTypeRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->require('foo');
    }
}
