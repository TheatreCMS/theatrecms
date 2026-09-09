<?php

namespace TheatreCMS\Theme;

/**
 * The computed HTML meta tags for a single frontend page. An empty string on
 * any field means "omit this tag" rather than emitting it with empty content.
 */
final class SeoMeta
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonicalUrl,
        public readonly string $ogImage,
        public readonly string $ogType,
        public readonly string $twitterCard,
    ) {
    }
}
