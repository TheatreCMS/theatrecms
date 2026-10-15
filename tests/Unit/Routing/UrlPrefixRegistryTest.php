<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Routing\UrlPrefixRegistry;

class UrlPrefixRegistryTest extends TestCase
{
    public function testDifferentPrefixesCanBeClaimed(): void
    {
        $registry = new UrlPrefixRegistry();

        $registry->claim('seasons');
        $registry->claim('genre');

        $this->addToAssertionCount(1);
    }

    public function testRejectsAPrefixClaimedTwice(): void
    {
        $registry = new UrlPrefixRegistry();
        $registry->claim('category');

        $this->expectException(InvalidArgumentException::class);

        $registry->claim('category');
    }
}
