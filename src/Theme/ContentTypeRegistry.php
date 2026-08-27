<?php

namespace TheatreCMS\Theme;

/**
 * Maps a built-in content type to the URL path segment used for its frontend routes.
 *
 * Defaults match the content type's own name (e.g. `seasons` serves under `/seasons`),
 * but a site can override the segment via the `content_types` key in `app/config.yaml`
 * — e.g. a theatre that calls its season listing "Shows" can serve it under `/shows`
 * without any code or template changes, since routes, menu links, and permalinks all
 * resolve the prefix through this registry rather than hardcoding it.
 */
class ContentTypeRegistry
{
    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'seasons' => 'seasons',
        'productions' => 'productions',
        'people' => 'people',
        'works' => 'works',
        'posts' => 'posts',
    ];

    /**
     * @var array<string, string>
     */
    private array $prefixes;

    /**
     * @param array<string, string> $overrides Content type => URL prefix, from config.
     */
    public function __construct(array $overrides = [])
    {
        $this->prefixes = array_merge(self::DEFAULTS, $overrides);
    }

    /**
     * Returns the URL path segment (no leading/trailing slash) for a content type.
     * Unknown types pass through unchanged, so new/custom types work without registration.
     */
    public function prefix(string $type): string
    {
        return $this->prefixes[$type] ?? $type;
    }
}
