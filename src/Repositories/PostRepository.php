<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Post;
use Doctrine\ORM\QueryBuilder;

class PostRepository extends BaseRepository
{
    protected string $entityClass = Post::class;

    public function create(array $args): Post
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

        $post = new Post($args['title'], $status, $args['content']);
        $slugSource = $args['slug'] ?? $args['title'];
        $post->setSlug($this->generateUniqueSlug($slugSource));

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.publishedAt', $alias), 'DESC')
            ->addOrderBy(sprintf('%s.id', $alias), 'DESC');
    }

    protected function applySearchFilter(QueryBuilder $builder, string $alias, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $builder->andWhere(sprintf('%s.title LIKE :search', $alias))
            ->setParameter('search', '%' . $search . '%');
    }

    protected function applyRequestedSort(QueryBuilder $builder, string $alias, string $sort, string $direction): bool
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        if ($sort === 'title') {
            $builder->orderBy(sprintf('%s.title', $alias), $direction)
                ->addOrderBy(sprintf('%s.id', $alias), 'ASC');
            return true;
        }

        if ($sort === 'publishedAt') {
            $builder->orderBy(sprintf('%s.publishedAt', $alias), $direction)
                ->addOrderBy(sprintf('%s.id', $alias), 'ASC');
            return true;
        }

        return false;
    }

    public function fetchPublished(): array
    {
        return $this->em->createQueryBuilder()
            ->select('p')
            ->from(Post::class, 'p')
            ->where('p.status = :status')
            ->setParameter('status', ContentStatus::PUBLISHED)
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateSlug(Post $post, string $slug): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            return;
        }

        $current = $post->getSlug();
        if ($current !== null && strcasecmp($current, $slug) === 0) {
            return;
        }

        $post->setSlug($this->generateUniqueSlug($slug));
    }
}
