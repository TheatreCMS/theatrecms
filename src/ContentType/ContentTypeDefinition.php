<?php

namespace TheatreCMS\ContentType;

use TheatreCMS\Auth\Capability;

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
     *        for `/press-releases/{slug}`
     * @param bool $hasArchive whether it gets a public archive (listing) page at `/{urlPrefix}`
     * @param string $capability capability required to manage its entries in the admin UI
     * @param bool $public whether its entries get frontend routes at all
     * @param string[] $supports standard entry fields it uses: 'editor', 'excerpt', 'featured_image'
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $singularLabel,
        public readonly string $urlPrefix,
        public readonly bool $hasArchive = true,
        public readonly string $capability = Capability::EDIT_POSTS,
        public readonly bool $public = true,
        public readonly array $supports = [
            'editor',
        ],
    ) {
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports);
    }

    public function adminSlug(): string
    {
        return strtolower(str_replace(' ', '_', $this->label));
    }
}
