<?php

namespace TheatreCMS\Services;

use TheatreCMS\ContentType\ContentRepositoryLocator;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Theme\TitleResolver;

/**
 * Supplies the menu-item tree editor with what an editor can link to: every public content
 * type (core and plugin-registered) with its items, so MenuController needs no content
 * repositories of its own.
 */
class MenuLinkTargetOptionsService
{
    public function __construct(
        private readonly ContentRepositoryLocator $repositories,
        private readonly TitleResolver $titleResolver,
    ) {
    }

    /**
     * @return list<array{type: string, label: string, singularLabel: string, hasArchive: bool,
     *         items: list<array{id: int, title: string}>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->repositories->publicTypes() as $type => $definition) {
            $items = [];

            foreach ($this->repositories->forType($type)?->fetchAll() ?? [] as $entity) {
                $items[] = ['id' => $entity->getId(), 'title' => $this->titleResolver->resolve($entity)];
            }

            $groups[] = [
                'type' => $type,
                'label' => $definition->label,
                'singularLabel' => $definition->singularLabel,
                'hasArchive' => $definition->hasArchive,
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * The content type a menu item may link to under this key, or null if it can't be linked to.
     */
    public function linkableType(string $type): ?ContentTypeDefinition
    {
        return $this->repositories->publicTypes()[$type] ?? null;
    }

    /**
     * Badge labels for the editor, link type => singular label, including custom links.
     *
     * @return array<string, string>
     */
    public function typeLabels(): array
    {
        $labels = [];

        foreach ($this->repositories->publicTypes() as $type => $definition) {
            $labels[$type] = $definition->singularLabel;
        }

        return $labels + [MenuItem::CUSTOM => 'Custom Link'];
    }
}
