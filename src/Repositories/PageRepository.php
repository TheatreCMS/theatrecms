<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Page;

class PageRepository extends BaseRepository
{
    protected string $entityClass = Page::class;

    public function create(array $args): Page
    {
        $args = array_merge([
            'title' => null,
            'status' => ContentStatus::DRAFT->value,
            'content' => null,
            'slug' => null,
        ], $args);

        if (empty($args['title'])) {
            throw new \InvalidArgumentException('Title is required.');
        }

        if (empty($args['content'])) {
            throw new \InvalidArgumentException('Content is required.');
        }

        $status = ContentStatus::tryFrom($args['status']);
        if ($status === null) {
            throw new \InvalidArgumentException('Invalid status.');
        }

        $page = new Page($args['title'], $status, $args['content']);
        $slugSource = $args['slug'] ?? $args['title'];
        $page->setSlug($this->generateUniqueSlug($slugSource));

        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    public function updateSlug(Page $page, string $slug): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            return;
        }

        $current = $page->getSlug();
        if ($current !== null && strcasecmp($current, $slug) === 0) {
            return;
        }

        $page->setSlug($this->generateUniqueSlug($slug));
    }
}