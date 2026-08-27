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
}
