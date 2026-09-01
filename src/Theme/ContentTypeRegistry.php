<?php

namespace TheatreCMS\Theme;

/**
 * Maps a built-in content type to the URL path segment and display label used for its
 * frontend routes and page titles.
 *
 * Defaults match the content type's own name (e.g. `seasons` serves under `/seasons`
 * with the label "Seasons"), but a site can override either via the `content_types` key
 * in `app/config.yaml` — e.g. a theatre that calls its season listing "Shows" can serve
 * it under `/shows` with "Shows" as the label everywhere (URLs, menu links, permalinks,
 * and page `<title>`s), without any code or template changes.
 *
 * A content type's archive (listing) page is enabled by default; setting `has_archive:
 * false` on a type disables it (its frontend route 404s) without affecting single-entity
 * routes, e.g. a site can turn off `/seasons` while keeping `/seasons/{slug}` working.
 */
class ContentTypeRegistry
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_PREFIXES = [
        'seasons' => 'seasons',
        'productions' => 'productions',
        'people' => 'people',
        'works' => 'works',
        'posts' => 'posts',
    ];

    /**
     * @var array<string, string>
     */
    private const DEFAULT_LABELS = [
        'seasons' => 'Seasons',
        'productions' => 'Productions',
        'people' => 'People',
        'works' => 'Works',
        'posts' => 'Posts',
    ];

    /**
     * @var array<string, string>
     */
    private array $prefixes;

    /**
     * @var array<string, string>
     */
    private array $labels;

    /**
     * @var array<string, bool>
     */
    private array $hasArchive = [];

    /**
     * @param array<string, mixed> $overrides Content type => URL prefix (shorthand string)
     *                                         or `['url_prefix' => ..., 'label' => ..., 'has_archive' => ...]`,
     *                                         from config.
     */
    public function __construct(array $overrides = [])
    {
        $this->prefixes = self::DEFAULT_PREFIXES;
        $this->labels = self::DEFAULT_LABELS;

        foreach ($overrides as $type => $override) {
            if (is_string($override)) {
                $override = ['url_prefix' => $override];
            }

            if (!is_array($override)) {
                continue;
            }

            if (isset($override['url_prefix'])) {
                $this->prefixes[$type] = (string) $override['url_prefix'];

                if (!isset($override['label'])) {
                    $this->labels[$type] = ucfirst((string) $override['url_prefix']);
                }
            }

            if (isset($override['label'])) {
                $this->labels[$type] = (string) $override['label'];
            }

            if (isset($override['has_archive'])) {
                $this->hasArchive[$type] = (bool) $override['has_archive'];
            }
        }
    }

    /**
     * Returns the URL path segment (no leading/trailing slash) for a content type.
     * Unknown types pass through unchanged, so new/custom types work without registration.
     */
    public function prefix(string $type): string
    {
        return $this->prefixes[$type] ?? $type;
    }

    /**
     * Returns the display label (e.g. for page titles and menu links) for a content type.
     * Unknown types fall back to a capitalized version of the type key.
     */
    public function label(string $type): string
    {
        return $this->labels[$type] ?? ucfirst($type);
    }

    /**
     * Whether a content type's archive (listing) page should be served. Defaults to true
     * for every type, including unknown/custom ones; disabled via `has_archive: false`
     * in the type's `content_types` config entry.
     */
    public function hasArchive(string $type): bool
    {
        return $this->hasArchive[$type] ?? true;
    }
}
