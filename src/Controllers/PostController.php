<?php

namespace TheatreCMS\Controllers;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheatreCMS\Enums\PostStatus;
use TheatreCMS\Repositories\PostRepository;

/**
 * @method PostRepository repository()
 */
class PostController extends BaseController
{
    public function __construct(PostRepository $repository, EntityManagerInterface $em)
    {
        $this->repository = $repository;
        $this->entityManager = $em;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        try {
            $this->repository->create([
                'title' => $data['title'] ?? null,
                'status' => $data['status'] ?? PostStatus::DRAFT->value,
                'content' => $data['content'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        return $response->withHeader('Location', '/admin/posts');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'postId' => 0,
            'title' => null,
            'status' => PostStatus::DRAFT->value,
            'content' => null,
            'slug' => null,
        ]);

        $post = $this->repository->fetch($data['postId']);

        if (!$post) {
            return $response->withStatus(404);
        }

        if (empty($data['title']) || empty($data['content'])) {
            return $response->withStatus(400);
        }

        $status = PostStatus::tryFrom($data['status']);
        if ($status === null) {
            return $response->withStatus(400);
        }

        $post->setTitle($data['title']);
        $post->setStatus($status);
        $post->setContent($data['content']);
        $post->touchModified();

        if ($status === PostStatus::PUBLISHED) {
            if ($post->getPublishedAt() === null) {
                $post->setPublishedAt(new DateTimeImmutable());
            }
        } else {
            $post->setPublishedAt(null);
        }

        if (!empty($data['slug'])) {
            $this->repository->updateSlug($post, $data['slug']);
        }

        $this->repository->update($post);

        return $response->withHeader('Location', '/admin/posts');
    }
}
