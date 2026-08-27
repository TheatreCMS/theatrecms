<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Theme\ContentTypeRegistry;

class ContentTypeRegistryTest extends TestCase
{
    public function testDefaultPrefixMatchesTypeName(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('seasons', $registry->prefix('seasons'));
    }

    public function testOverridePrefixFromConfig(): void
    {
        $registry = new ContentTypeRegistry(['seasons' => 'shows']);

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

    public function testShorthandOverrideDerivesLabelFromPrefix(): void
    {
        $registry = new ContentTypeRegistry(['seasons' => 'shows']);

        $this->assertSame('shows', $registry->prefix('seasons'));
        $this->assertSame('Shows', $registry->label('seasons'));
    }

    public function testExpandedOverrideSetsIndependentLabel(): void
    {
        $registry = new ContentTypeRegistry([
            'seasons' => ['url_prefix' => 'shows', 'label' => 'Our Shows'],
        ]);

        $this->assertSame('shows', $registry->prefix('seasons'));
        $this->assertSame('Our Shows', $registry->label('seasons'));
    }

    public function testUnknownTypeLabelFallsBackToCapitalizedType(): void
    {
        $registry = new ContentTypeRegistry();

        $this->assertSame('Widgets', $registry->label('widgets'));
    }
}
