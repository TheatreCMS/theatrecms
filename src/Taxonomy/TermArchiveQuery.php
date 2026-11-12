<?php

namespace TheatreCMS\Taxonomy;

use TheatreCMS\ContentType\ContentRepositoryLocator;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\TermRelationshipRepository;

/**
 * Collects the publicly visible content items carrying a term, for its frontend archive page.
 * Term assignments are soft references keyed by content type, so each type the taxonomy applies
 * to is loaded through its own repository (see `ContentRepositoryLocator`). Types that aren't
 * public, aren't registered, or have no repository are skipped.
 */
class TermArchiveQuery
{
    public function __construct(
        private readonly TermRelationshipRepository $relationships,
        private readonly ContentRepositoryLocator $repositories,
    ) {
    }

    /**
     * @return array<string, array<int, object>> content type => items, in the taxonomy's content-type order
     */
    public function itemsFor(Term $term, TaxonomyDefinition $taxonomy): array
    {
        $items = [];

        foreach ($taxonomy->contentTypes as $contentType) {
            $repository = $this->repositories->forType($contentType);

            if ($repository === null) {
                continue;
            }

            $ids = $this->relationships->contentIdsFor($term, $contentType);
            $items[$contentType] = $repository->fetchPublicByIds($ids);
        }

        return $items;
    }
}
