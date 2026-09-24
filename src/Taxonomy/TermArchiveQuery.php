<?php

namespace TheatreCMS\Taxonomy;

use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\BaseRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;

/**
 * Collects the publicly visible content items carrying a term, for its frontend archive page.
 * Term assignments are soft references keyed by content type, so each type the taxonomy applies
 * to is loaded through its own repository; types with no repository mapped here are skipped.
 */
class TermArchiveQuery
{
    /**
     * @param array<string, BaseRepository> $repositories singular content type => repository,
     *        e.g. `['work' => WorkRepository]`
     */
    public function __construct(
        private readonly TermRelationshipRepository $relationships,
        private readonly array $repositories,
    ) {
    }

    /**
     * @return array<string, array<int, object>> content type => items, in the taxonomy's content-type order
     */
    public function itemsFor(Term $term, TaxonomyDefinition $taxonomy): array
    {
        $items = [];

        foreach ($taxonomy->contentTypes as $contentType) {
            if (!isset($this->repositories[$contentType])) {
                continue;
            }

            $ids = $this->relationships->contentIdsFor($term, $contentType);
            $items[$contentType] = $this->repositories[$contentType]->fetchPublicByIds($ids);
        }

        return $items;
    }
}
