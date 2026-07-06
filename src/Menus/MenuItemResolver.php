<?php

namespace TheatreCMS\Menus;

use TheatreCMS\Enums\MenuItemType;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;

/**
 * Resolves a MenuItem's rendered URL and label by looking up its linked
 * Page/Post/Production/Season (when applicable). Kept separate from the entities
 * themselves since entities should not reach into repositories/the container.
 */
class MenuItemResolver
{
    /**
     * Conventional listing-page URLs for each content type's archive. A menu
     * item of a given type with no targetId links to that type's archive
     * rather than a specific piece of content — no separate "archive" enum
     * case or DB column needed.
     *
     * @var array<string, string>
     */
    private const ARCHIVE_URLS = [
        'page' => '/pages',
        'post' => '/posts',
        'production' => '/productions',
        'season' => '/seasons',
    ];

    /**
     * @var array<string, string>
     */
    private const ARCHIVE_LABELS = [
        'page' => 'All Pages',
        'post' => 'All Posts',
        'production' => 'All Productions',
        'season' => 'All Seasons',
    ];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PostRepository $postRepository,
        private readonly ProductionRepository $productionRepository,
        private readonly SeasonRepository $seasonRepository
    ) {
    }

    /**
     * Returns the item's target URL, or null if the linked content no longer exists
     * (e.g. the linked Page/Post/Production/Season was deleted).
     */
    public function resolveUrl(MenuItem $item): ?string
    {
        if ($item->getLinkType() !== MenuItemType::CUSTOM && !$item->getTargetId()) {
            return self::ARCHIVE_URLS[$item->getLinkType()->value];
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

        if ($item->getLinkType() !== MenuItemType::CUSTOM && !$item->getTargetId()) {
            return self::ARCHIVE_LABELS[$item->getLinkType()->value];
        }

        $title = match ($item->getLinkType()) {
            MenuItemType::CUSTOM => null,
            MenuItemType::PAGE => $item->getTargetId() ? $this->pageRepository->fetch($item->getTargetId())?->getTitle() : null,
            MenuItemType::POST => $item->getTargetId() ? $this->postRepository->fetch($item->getTargetId())?->getTitle() : null,
            MenuItemType::PRODUCTION => $item->getTargetId() ? $this->productionRepository->fetch($item->getTargetId())?->getName() : null,
            MenuItemType::SEASON => $item->getTargetId() ? $this->seasonRepository->fetch($item->getTargetId())?->getLabel() : null,
        };

        return $title ?? '(untitled)';
    }

    private function resolvePageUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $page = $this->pageRepository->fetch($item->getTargetId());

        return $page ? '/' . $page->getSlug() : null;
    }

    private function resolvePostUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $post = $this->postRepository->fetch($item->getTargetId());

        return $post ? '/posts/' . $post->getSlug() : null;
    }

    private function resolveProductionUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $production = $this->productionRepository->fetch($item->getTargetId());

        return $production ? '/seasons/' . $production->getSeason()->getSlug() . '/' . $production->getSlug() : null;
    }

    private function resolveSeasonUrl(MenuItem $item): ?string
    {
        if (!$item->getTargetId()) {
            return null;
        }

        $season = $this->seasonRepository->fetch($item->getTargetId());

        return $season ? '/seasons/' . $season->getSlug() : null;
    }
}
