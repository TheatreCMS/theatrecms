<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Routing\UrlPrefixRegistry;

class UrlPrefixRegistryTest extends TestCase
{
    public function testDifferentOwnersCanClaimDifferentPrefixes(): void
    {
        $registry = new UrlPrefixRegistry();

        $registry->claim('seasons', 'content type "seasons"');
        $registry->claim('genre', 'taxonomy "genre"');

        $this->addToAssertionCount(1);
    }

    public function testSameOwnerCanClaimItsPrefixAgain(): void
    {
        $registry = new UrlPrefixRegistry();

        $registry->claim('genre', 'taxonomy "genre"');
        $registry->claim('genre', 'taxonomy "genre"');

        $this->addToAssertionCount(1);
    }

    public function testRejectsPrefixClaimedByAnotherOwner(): void
    {
        $registry = new UrlPrefixRegistry();
        $registry->claim('category', 'taxonomy "post_category"');

        $this->expectException(InvalidArgumentException::class);

        $registry->claim('category', 'content type "category"');
    }

    public function testValidatesPrefixBeforeClaiming(): void
    {
        $registry = new UrlPrefixRegistry();

        $this->expectException(InvalidArgumentException::class);

        $registry->claim('admin', 'content type "admin"');
    }
}
