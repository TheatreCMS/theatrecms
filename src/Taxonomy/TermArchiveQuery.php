<?php

namespace TheatreCMS\Taxonomy;

use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Controllers\EntryController;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\BaseRepository;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;

/**
 * Collects the publicly visible content items carrying a term, for its frontend archive page.
 * Term assignments are soft references keyed by content type, so each type the taxonomy applies
 * to is loaded through its own repository: entry types (those served by `EntryController`)
 * through `EntryRepository::forType()`, the model-backed core types through the map given to
 * the constructor. Types that aren't public, aren't registered, or have no repository are skipped.
 */
class TermArchiveQuery
{
    /**
     * @param array<string, BaseRepository> $repositories repositories of the model-backed core types,
     *        singular content type => repository, e.g. `['work' => WorkRepository]`
     */
    public function __construct(
        private readonly TermRelationshipRepository $relationships,
        private readonly ContentTypeRegistry $contentTypes,
        private readonly EntryRepository $entries,
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
            $repository = $this->repositoryFor($contentType);

            if ($repository === null) {
                continue;
            }

            $ids = $this->relationships->contentIdsFor($term, $contentType);
            $items[$contentType] = $repository->fetchPublicByIds($ids);
        }

        return $items;
    }

    private function repositoryFor(string $contentType): ?BaseRepository
    {
        $definition = $this->contentTypes->get($contentType);

        // A non-public type's items have no pages of their own, so a public archive never lists them.
        if ($definition === null || !$definition->public) {
            return null;
        }

        if (isset($this->repositories[$contentType])) {
            return $this->repositories[$contentType];
        }

        if (is_a($definition->controller, EntryController::class, true)) {
            return $this->entries->forType($contentType);
        }

        return null;
    }
}
