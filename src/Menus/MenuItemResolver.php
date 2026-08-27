<?php

namespace TheatreCMS\Menus;

use TheatreCMS\Enums\MenuItemType;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Theme\PermalinkResolver;

/**
 * Resolves a MenuItem's rendered URL and label by looking up its linked
 * Page/Post/Production/Season (when applicable). Kept separate from the entities
 * themselves since entities should not reach into repositories/the container.
 */
class MenuItemResolver
{
    /**
     * Content-type keys (see `ContentTypeRegistry`) for each linkable menu item type. A
     * menu item of a given type with no targetId links to that type's archive rather
     * than a specific piece of content — no separate "archive" enum case or DB column
     * needed. Pages are deliberately excluded: they're always individually linked, with
     * no archive/listing page.
     *
     * @var array<string, string>
     */
    private const ARCHIVE_TYPES = [
        'post' => 'posts',
        'production' => 'productions',
        'season' => 'seasons',
    ];

    /**
     * @var array<string, string>
     */
    private const ARCHIVE_LABELS = [
        'post' => 'All Posts',
        'production' => 'All Productions',
        'season' => 'All Seasons',
    ];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PostRepository $postRepository,
        private readonly ProductionRepository $productionRepository,
        private readonly SeasonRepository $seasonRepository,
        private readonly PermalinkResolver $permalinkResolver
    ) {
    }

    /**
     * Returns the item's target URL, or null if the linked content no longer exists
     * (e.g. the linked Page/Post/Production/Season was deleted).
     */
    public function resolveUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId() && isset(self::ARCHIVE_TYPES[$item->getLinkType()->value])) {
            return $this->permalinkResolver->archiveUrl(self::ARCHIVE_TYPES[$item->getLinkType()->value]);
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

        if (!$item->getTargetId() && isset(self::ARCHIVE_LABELS[$item->getLinkType()->value])) {
            return self::ARCHIVE_LABELS[$item->getLinkType()->value];
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
}
