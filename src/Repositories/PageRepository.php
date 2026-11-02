<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\QueryBuilder;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Page;
use TheatreCMS\Traits\ManagesHierarchy;

class PageRepository extends BaseRepository
{
    use ManagesHierarchy;

    protected string $entityClass = Page::class;

    /**
     * @throws \InvalidArgumentException for a missing title or content, an invalid status, or an
     *         unknown parent
     */
    public function create(array $args): Page
    {
        $args = array_merge([
            'title' => null,
            'status' => ContentStatus::DRAFT->value,
            'content' => null,
            'slug' => null,
            'parentId' => null,
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

        $parent = $this->resolveParent($args['parentId']);

        $page = new Page($args['title'], $status, $args['content']);
        $page->setParent($parent);
        $slugSource = $args['slug'] ?? $args['title'];
        $page->setSlug($this->generateUniqueSlug($slugSource, ['parent' => $parent]));

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

        $page->setSlug($this->generateUniqueSlug($slug, ['parent' => $page->getParent()]));
    }

    /**
     * The page with this id, or null for none (top level).
     *
     * @throws \InvalidArgumentException for an id that matches no page
     */
    public function resolveParent(mixed $parentId): ?Page
    {
        if (empty($parentId)) {
            return null;
        }

        $parent = $this->fetch((int) $parentId);
        if (!$parent instanceof Page) {
            throw new \InvalidArgumentException('Parent page not found.');
        }

        return $parent;
    }

    /**
     * Deletes the page, moving its children up to its own parent.
     */
    public function delete($item): void
    {
        $this->reparentChildren($item);
        parent::delete($item);
    }

    protected function applyPublicFilter(QueryBuilder $builder, string $alias): void
    {
        $builder->andWhere(sprintf('%s.status = :publicStatus', $alias))
            ->setParameter('publicStatus', ContentStatus::PUBLISHED);
    }
}
