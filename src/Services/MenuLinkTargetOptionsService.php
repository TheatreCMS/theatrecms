<?php

namespace TheatreCMS\Services;

use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;

/**
 * Supplies the pages/posts/productions/seasons option lists the menu-item
 * tree editor uses to let an editor pick a link target, so MenuController
 * doesn't need each content repository injected individually just to call
 * fetchAll().
 */
class MenuLinkTargetOptionsService
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PostRepository $postRepository,
        private readonly ProductionRepository $productionRepository,
        private readonly SeasonRepository $seasonRepository,
    ) {
    }

    public function getPages(): array
    {
        return $this->pageRepository->fetchAll();
    }

    public function getPosts(): array
    {
        return $this->postRepository->fetchAll();
    }

    public function getProductions(): array
    {
        return $this->productionRepository->fetchAll();
    }

    public function getSeasons(): array
    {
        return $this->seasonRepository->fetchAll();
    }
}
