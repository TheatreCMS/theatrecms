<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Page;

class PageRepository extends BaseRepository
{
    protected string $entityClass = Page::class;

    public function create(array $args): Page
    {
        $args = array_merge([
            'title' => null,
            'content' => null,
            'slug' => null,
        ], $args);

        if (empty($args['title'])) {
            throw new \InvalidArgumentException('Title is required.');
        }

        if (empty($args['content'])) {
            throw new \InvalidArgumentException('Content is required.');
        }

        $page = new Page($args['title'], $args['content']);
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