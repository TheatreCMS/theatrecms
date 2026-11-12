<?php

namespace TheatreCMS\ContentType;

use TheatreCMS\Controllers\EntryController;
use TheatreCMS\Repositories\BaseRepository;
use TheatreCMS\Repositories\EntryRepository;

/**
 * Finds the repository holding a public content type's items, for code that stores soft
 * references keyed by content type (term assignments, menu items) and must load the items back.
 *
 * Entry types (those served by `EntryController`: posts and plugin types) resolve through
 * `EntryRepository::forType()`; the model-backed core types through the map given to the
 * constructor. Types that aren't registered, aren't public or have no repository have none.
 */
class ContentRepositoryLocator
{
    /**
     * @param array<string, BaseRepository> $repositories repositories of the model-backed core types,
     *        singular content type => repository, e.g. `['work' => WorkRepository]`
     */
    public function __construct(
        private readonly ContentTypeRegistry $contentTypes,
        private readonly EntryRepository $entries,
        private readonly array $repositories,
    ) {
    }

    /**
     * The repository for a public type's items, or null when the type has none.
     */
    public function forType(string $contentType): ?BaseRepository
    {
        $definition = $this->contentTypes->get($contentType);

        // A non-public type's items have no pages of their own, so nothing public links to them.
        if ($definition === null || !$definition->public) {
            return null;
        }

        return $this->repositoryFor($definition);
    }

    /**
     * Every public type that has a repository, in registration order.
     *
     * @return array<string, ContentTypeDefinition>
     */
    public function publicTypes(): array
    {
        return array_filter(
            $this->contentTypes->all(),
            fn(ContentTypeDefinition $definition): bool => $definition->public
                && $this->repositoryFor($definition) !== null
        );
    }

    private function repositoryFor(ContentTypeDefinition $definition): ?BaseRepository
    {
        if (isset($this->repositories[$definition->name])) {
            return $this->repositories[$definition->name];
        }

        if (is_a($definition->controller, EntryController::class, true)) {
            return $this->entries->forType($definition->name);
        }

        return null;
    }
}
