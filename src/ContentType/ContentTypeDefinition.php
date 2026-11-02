<?php

namespace TheatreCMS\ContentType;

use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\EntryController;

/**
 * A registered content type (e.g. `press_release`): its machine name, how its URLs are
 * built, who may manage it, and which standard fields its entries use.
 *
 * Content types are declared in code via `register_content_type()` rather than stored in
 * the database; only their entries are rows (see `TheatreCMS\Models\Entry`). The built-in
 * types (season, production, person, work, post) are seeded by `ContentTypeRegistry`.
 */
final class ContentTypeDefinition
{
    /**
     * @param string $name singular machine key, stored in `entries.content_type` and
     *        `content_meta.content_type` (e.g. 'press_release')
     * @param string $label plural display label, e.g. "Press Releases"
     * @param string $singularLabel singular display label, e.g. "Press Release"
     * @param string $urlPrefix URL path segment its entries are served under, e.g. `press-releases`
     *        for `/press-releases/{slug}`; empty for the one type served at the site root (pages)
     * @param bool $hasArchive whether it gets a public archive (listing) page at `/{urlPrefix}`
     * @param string $capability capability required to manage its entries in the admin UI
     * @param bool $public whether its entries get frontend routes at all
     * @param string[] $supports standard entry fields it uses: 'editor', 'excerpt', 'featured_image'
     * @param bool $hierarchical whether its items can have a parent item of the same type, served at
     *        the parent's URL plus their own slug (`/about/board-of-directors`); the model must use `HasParent`
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $singularLabel,
        public readonly string $urlPrefix,
        public readonly string $controller = EntryController::class,
        public readonly bool $hasArchive = true,
        public readonly string $capability = Capability::MANAGE_POSTS,
        public readonly bool $public = true,
        public readonly array $supports = [
            'editor',
        ],
        public readonly bool $hierarchical = false,
    ) {
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports);
    }

    public function adminSlug(): string
    {
        return 'admin/' . $this->directoryName();
    }

    /**
     * The directory its theme templates live in, e.g. `seasons/single.html.twig`.
     */
    public function templateDir(): string
    {
        return $this->directoryName();
    }

    /**
     * The URL prefix, or for the root type, which has none, its label as a path segment
     * (`pages`), so its admin screens and templates still get a directory of their own.
     */
    private function directoryName(): string
    {
        if ($this->urlPrefix !== '') {
            return $this->urlPrefix;
        }

        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($this->label)), '-');
    }
}
