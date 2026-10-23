<?php

namespace TheatreCMS\Menus;

use TheatreCMS\Enums\MenuItemType;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\ContentType\PermalinkResolver;

/**
 * Resolves a MenuItem's rendered URL and label by looking up its linked
 * Page/Post/Production/Season (when applicable). Kept separate from the entities
 * themselves since entities should not reach into repositories/the container.
 */
class MenuItemResolver
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PostRepository $postRepository,
        private readonly ProductionRepository $productionRepository,
        private readonly SeasonRepository $seasonRepository,
        private readonly PermalinkResolver $permalinkResolver,
        private readonly ContentTypeRegistry $contentTypes
    ) {
    }

    /**
     * Returns the item's target URL, or null if the linked content no longer exists
     * (e.g. the linked Page/Post/Production/Season was deleted).
     */
    public function resolveUrl(MenuItem $item): ?string
    {
        if ($this->linksToArchive($item)) {
            return $this->permalinkResolver->archiveUrl($item->getLinkType()->value);
        }

        return match ($item->getLinkType()) {
            MenuItemType::CUSTOM => $item->getCustomUrl(),
            MenuItemType::PAGE => $this->resolvePageUrl($item),
            MenuItemType::POST => $this->resolvePostUrl($item),
            MenuItemType::PRODUCTION => $this->resolveProductionUrl($item),
            MenuItemType::SEASON => $this->resolveSeasonUrl($item),
        };
    }

    public function resolveLabel(MenuItem $item): string
    {
        if (!empty($item->getLabel())) {
            return $item->getLabel();
        }

        if ($this->linksToArchive($item)) {
            return 'All ' . $this->contentTypes->label($item->getLinkType()->value);
        }

        return $this->resolveSourceTitle($item) ?? '(untitled)';
    }

    /**
     * Returns the linked Page/Post/Production/Season's own title, regardless of
     * any label override on the item. Used by the editor to tell the admin what
     * content a renamed item still points to. Null for custom links and archive
     * links (no specific piece of content to name).
     */
    public function resolveSourceTitle(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        return match ($item->getLinkType()) {
            MenuItemType::CUSTOM => null,
            MenuItemType::PAGE => $this->pageRepository->fetch($item->getTargetId())?->getTitle(),
            MenuItemType::POST => $this->postRepository->fetch($item->getTargetId())?->getTitle(),
            MenuItemType::PRODUCTION => $this->productionRepository->fetch($item->getTargetId())?->getName(),
            MenuItemType::SEASON => $this->seasonRepository->fetch($item->getTargetId())?->getLabel(),
        };
    }

    private function resolvePageUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $page = $this->pageRepository->fetch($item->getTargetId());

        return $page ? $this->permalinkResolver->resolve($page) : null;
    }

    private function resolvePostUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $post = $this->postRepository->fetch($item->getTargetId());

        return $post ? $this->permalinkResolver->resolve($post) : null;
    }

    private function resolveProductionUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $production = $this->productionRepository->fetch($item->getTargetId());

        return $production ? $this->permalinkResolver->resolve($production) : null;
    }

    private function resolveSeasonUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $season = $this->seasonRepository->fetch($item->getTargetId());

        return $season ? $this->permalinkResolver->resolve($season) : null;
    }

    /**
     * A menu item with no target links to its content type's archive rather than a specific piece
     * of content, so no separate "archive" enum case or DB column is needed. Its link type value
     * (`post`, `production`, `season`) is the content-type key. Pages and custom links aren't
     * content types, so they are always linked individually.
     */
    private function linksToArchive(MenuItem $item): bool
    {
        return !$item->getTargetId() && $this->contentTypes->has($item->getLinkType()->value);
    }
}
