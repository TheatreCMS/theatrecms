<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Enums\PostStatus;
use TheatreCMS\Models\Post;

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
}
