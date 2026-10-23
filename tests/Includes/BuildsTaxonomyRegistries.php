<?php

namespace TheatreCMS\Tests\Includes;

use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Routing\UrlPrefixRegistry;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Builds a TaxonomyRegistry for tests that don't care which content types exist, only that the
 * ones their taxonomies apply to are registered.
 */
trait BuildsTaxonomyRegistries
{
    private const CORE_CONTENT_TYPES = ['season', 'production', 'person', 'work', 'post'];

    /**
     * A TaxonomyRegistry whose taxonomies may apply to any of core's content types. The content
     * types claim their URL prefixes in a registry of their own, so `$urlPrefixes` only ever holds
     * the taxonomies' prefixes.
     */
    protected static function taxonomyRegistry(?UrlPrefixRegistry $urlPrefixes = null): TaxonomyRegistry
    {
        $contentTypes = new ContentTypeRegistry();
        foreach (self::CORE_CONTENT_TYPES as $type) {
            $contentTypes->register($type);
        }

        return new TaxonomyRegistry($contentTypes, $urlPrefixes);
    }
}
