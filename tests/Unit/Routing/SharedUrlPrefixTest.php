<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Routing\UrlPrefixRegistry;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Content types and taxonomies sharing one UrlPrefixRegistry, as ServiceRegistrar wires them.
 */
class SharedUrlPrefixTest extends TestCase
{
    public function testTaxonomyCannotUseAContentTypesPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        new ContentTypeRegistry([], $urlPrefixes);

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($urlPrefixes))->register('season_tag', ['season'], ['url_prefix' => 'seasons']);
    }

    public function testContentTypeCannotUseATaxonomysPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        (new TaxonomyRegistry($urlPrefixes))->register('genre', ['work'], ['url_prefix' => 'shows']);

        $this->expectException(InvalidArgumentException::class);

        new ContentTypeRegistry(['seasons' => ['urlPrefix' => 'shows']], $urlPrefixes);
    }

    public function testTaxonomyWithoutArchivesMayShareAContentTypesPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        new ContentTypeRegistry([], $urlPrefixes);

        $definition = (new TaxonomyRegistry($urlPrefixes))
            ->register('season_tag', ['season'], ['url_prefix' => 'seasons', 'has_archive' => false]);

        $this->assertSame('seasons', $definition->urlPrefix);
    }

    public function testContentTypeOverrideCannotUseAReservedPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ContentTypeRegistry(['seasons' => ['urlPrefix' => 'admin']]);
    }
}
