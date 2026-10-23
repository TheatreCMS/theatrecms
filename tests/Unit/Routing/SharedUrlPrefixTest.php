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
        $contentTypes = new ContentTypeRegistry([], $urlPrefixes);
        $contentTypes->register('season', ['urlPrefix' => 'seasons']);

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($contentTypes, $urlPrefixes))
            ->register('season_tag', ['season'], ['urlPrefix' => 'seasons']);
    }

    public function testContentTypeCannotUseATaxonomysPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        $contentTypes = new ContentTypeRegistry(['season' => ['urlPrefix' => 'shows']], $urlPrefixes);
        $contentTypes->register('work');
        (new TaxonomyRegistry($contentTypes, $urlPrefixes))->register('genre', ['work'], ['urlPrefix' => 'shows']);

        $this->expectException(InvalidArgumentException::class);

        $contentTypes->register('season', ['urlPrefix' => 'seasons']);
    }

    public function testTaxonomyWithoutArchivesCannotUseAContentTypesPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        $contentTypes = new ContentTypeRegistry([], $urlPrefixes);
        $contentTypes->register('season', ['urlPrefix' => 'seasons']);

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($contentTypes, $urlPrefixes))
            ->register('season_tag', ['season'], ['urlPrefix' => 'seasons', 'hasArchive' => false]);
    }

    public function testTaxonomyCannotTakeARegisteredContentTypesDerivedPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        $contentTypes = new ContentTypeRegistry([], $urlPrefixes);
        $contentTypes->register('post');
        $contentTypes->register('press_release');

        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry($contentTypes, $urlPrefixes))->register('press_release', ['post']);
    }

    public function testContentTypeCannotTakeATaxonomysDerivedPrefix(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        $contentTypes = new ContentTypeRegistry([], $urlPrefixes);
        $contentTypes->register('post');
        (new TaxonomyRegistry($contentTypes, $urlPrefixes))->register('press_release', ['post']);

        $this->expectException(InvalidArgumentException::class);

        $contentTypes->register('press_release');
    }

    public function testContentTypeCannotTakeThePrefixOfATaxonomyWithoutArchives(): void
    {
        $urlPrefixes = new UrlPrefixRegistry();
        $contentTypes = new ContentTypeRegistry([], $urlPrefixes);
        $contentTypes->register('post');
        (new TaxonomyRegistry($contentTypes, $urlPrefixes))
            ->register('press_release', ['post'], ['hasArchive' => false]);

        $this->expectException(InvalidArgumentException::class);

        $contentTypes->register('press_release');
    }

    public function testContentTypeOverrideCannotUseAReservedPrefix(): void
    {
        $registry = new ContentTypeRegistry(['season' => ['urlPrefix' => 'admin']]);

        $this->expectException(InvalidArgumentException::class);

        $registry->register('season', ['urlPrefix' => 'seasons']);
    }
}
