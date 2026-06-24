<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Enums\PostStatus;
use TheatreCMS\Models\Post;
use Doctrine\ORM\QueryBuilder;

class PostRepository extends BaseRepository
{
    protected string $entityClass = Post::class;

    public function create(array $args): Post
    {
        $args = array_merge([
            'title' => null,
            'status' => PostStatus::DRAFT->value,
            'content' => null,
            'slug' => null,
        ], $args);

        if (empty($args['title'])) {
            throw new \InvalidArgumentException('Title is required.');
        }

        if (empty($args['content'])) {
            throw new \InvalidArgumentException('Content is required.');
        }

        $status = PostStatus::tryFrom($args['status']);
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

    public function fetchPublished(): array
    {
        return $this->em->createQueryBuilder()
            ->select('p')
            ->from(Post::class, 'p')
            ->where('p.status = :status')
            ->setParameter('status', PostStatus::PUBLISHED)
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
