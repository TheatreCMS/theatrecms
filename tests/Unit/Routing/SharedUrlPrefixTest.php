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
        (new ContentTypeRegistry([], $urlPrefixes))->register('seasons');

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($urlPrefixes))->register('season_tag', ['season'], ['urlPrefix' => 'seasons']);
    }

    public function testContentTypeCannotUseATaxonomysPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        (new TaxonomyRegistry($urlPrefixes))->register('genre', ['work'], ['urlPrefix' => 'shows']);
        $registry = new ContentTypeRegistry(['seasons' => ['urlPrefix' => 'shows']], $urlPrefixes);

        $this->expectException(InvalidArgumentException::class);

        $registry->register('seasons');
    }

    public function testTaxonomyWithoutArchivesCannotUseAContentTypesPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        (new ContentTypeRegistry([], $urlPrefixes))->register('seasons');

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($urlPrefixes))
            ->register('season_tag', ['season'], ['urlPrefix' => 'seasons', 'hasArchive' => false]);
    }

    public function testTaxonomyCannotTakeARegisteredContentTypesDerivedPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        (new ContentTypeRegistry([], $urlPrefixes))->register('press_release');

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($urlPrefixes))->register('press_release', ['post']);
    }

    public function testContentTypeCannotTakeATaxonomysDerivedPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        (new TaxonomyRegistry($urlPrefixes))->register('press_release', ['post']);
        $registry = new ContentTypeRegistry([], $urlPrefixes);

        $this->expectException(InvalidArgumentException::class);

        $registry->register('press_release');
    }

    public function testContentTypeCannotTakeThePrefixOfATaxonomyWithoutArchives(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        (new TaxonomyRegistry($urlPrefixes))->register('press_release', ['post'], ['hasArchive' => false]);
        $registry = new ContentTypeRegistry([], $urlPrefixes);

        $this->expectException(InvalidArgumentException::class);

        $registry->register('press_release');
    }

    public function testContentTypeOverrideCannotUseAReservedPrefix(): void
    {
        $registry = new ContentTypeRegistry(['seasons' => ['urlPrefix' => 'admin']]);

        $this->expectException(InvalidArgumentException::class);

        $registry->register('seasons');
    }
}
