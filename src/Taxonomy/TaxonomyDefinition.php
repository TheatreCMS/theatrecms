<?php

namespace TheatreCMS\Taxonomy;

use TheatreCMS\Auth\Capability;

/**
 * A registered taxonomy (e.g. `genre` for works, `post_category` for posts): its machine
 * name, the content types it can be attached to, and how it is presented in the admin UI.
 *
 * Taxonomies are declared in code via `register_taxonomy()` rather than stored in the
 * database; only their terms are rows (see `TheatreCMS\Models\Term`).
 */
final class TaxonomyDefinition
{
    /**
     * @param string $name machine key stored in `terms.taxonomy` (max 32 chars)
     * @param string[] $contentTypes singular content-type keys, matching `content_meta.content_type`
     *        (e.g. 'work', 'post')
     * @param string $label plural display label, e.g. "Genres"
     * @param string $singularLabel singular display label, e.g. "Genre"
     * @param bool $multiple whether a content item may carry more than one term of this taxonomy
     * @param string $capability capability required to manage this taxonomy's terms in the admin UI
     */
    public function __construct(
        public readonly string $name,
        public readonly array $contentTypes,
        public readonly string $label,
        public readonly string $singularLabel,
        public readonly bool $multiple = true,
        public readonly string $capability = Capability::MANAGE_OPTIONS,
    ) {
    }

    public function appliesTo(string $contentType): bool
    {
        return in_array($contentType, $this->contentTypes, true);
    }
}
