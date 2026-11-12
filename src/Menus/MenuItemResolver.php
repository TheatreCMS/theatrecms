<?php

namespace TheatreCMS\Menus;

use TheatreCMS\ContentType\ContentRepositoryLocator;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\ContentType\PermalinkResolver;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Theme\TitleResolver;

/**
 * Resolves a MenuItem's rendered URL and label by looking up the content it links to.
 * Kept separate from the entities themselves since entities should not reach into
 * repositories/the container.
 *
 * An item's link type is `custom` or a content-type key. With a target id it links to that
 * item of the type; without one, to the type's archive.
 */
class MenuItemResolver
{
    public function __construct(
        private readonly ContentRepositoryLocator $repositories,
        private readonly ContentTypeRegistry $contentTypes,
        private readonly PermalinkResolver $permalinkResolver,
        private readonly TitleResolver $titleResolver,
    ) {
    }

    /**
     * Returns the item's target URL, or null if it no longer resolves: the linked content was
     * deleted, or its type was unregistered, made non-public or lost its archive.
     */
    public function resolveUrl(MenuItem $item): ?string
    {
        if ($item->isCustom()) {
            return $item->getCustomUrl();
        }

        if ($this->linksToArchive($item)) {
            return $this->permalinkResolver->archiveUrl($item->getLinkType());
        }

        $target = $this->target($item);

        return $target !== null ? $this->permalinkResolver->resolve($target) : null;
    }

    public function resolveLabel(MenuItem $item): string
    {
        if (!empty($item->getLabel())) {
            return $item->getLabel();
        }

        if ($this->linksToArchive($item)) {
            return 'All ' . $this->contentTypes->label($item->getLinkType());
        }

        return $this->resolveSourceTitle($item) ?? '(untitled)';
    }

    /**
     * Returns the linked content's own title, regardless of any label override on the item.
     * Used by the editor to tell the admin what content a renamed item still points to. Null for
     * custom links and archive links (no specific piece of content to name).
     */
    public function resolveSourceTitle(MenuItem $item): ?string
    {
        $target = $this->target($item);

        return $target !== null ? $this->titleResolver->resolve($target) : null;
    }

    private function target(MenuItem $item): ?object
    {
        if ($item->isCustom() || !$item->getTargetId()) {
            return null;
        }

        return $this->repositories->forType($item->getLinkType())?->fetch($item->getTargetId());
    }

    /**
     * A menu item with no target links to its content type's archive rather than a specific piece
     * of content, so no separate "archive" link type or DB column is needed.
     */
    private function linksToArchive(MenuItem $item): bool
    {
        if ($item->isCustom() || $item->getTargetId()) {
            return false;
        }

        $definition = $this->contentTypes->get($item->getLinkType());

        return $definition !== null && $definition->public && $definition->hasArchive;
    }
}
