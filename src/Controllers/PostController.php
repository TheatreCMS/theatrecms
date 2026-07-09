<?php

namespace TheatreCMS\Controllers;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Enums\PostStatus;
use TheatreCMS\Repositories\PostRepository;

/**
 * @method PostRepository repository()
 */
class PostController extends BaseController
{
    public function __construct(PostRepository $repository, EntityManagerInterface $em, Twig $twig)
    {
        $this->repository = $repository;
        $this->entityManager = $em;
        $this->twig       = $twig;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/posts/index.html.twig',
            $this->buildPaginatedViewData(
                $request,
                $this->repository,
                'posts',
                '/admin/posts',
                ['statuses' => PostStatus::labels()]
            )
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/posts/create.html.twig', [
            'statuses' => PostStatus::labels(),
        ]);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $post = $this->repository->fetch($args['id']);

        if (!$post) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/posts/edit.html.twig', [
            'post'     => $post,
            'statuses' => PostStatus::labels(),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $post = $this->repository->fetch($args['id']);
        if ($post) {
            try {
                $this->repository->delete($post);
            } catch (\Exception $e) {
                trigger_error("Unable to delete post: {$e->getMessage()}");
            }
        }

        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'posts',
            '/admin/posts',
            [
                'statuses' => PostStatus::labels(),
                'status_labels' => PostStatus::labels(),
            ]
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/posts/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/posts');
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create post. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        try {
            $this->repository->create([
                'title' => $data['title'] ?? null,
                'status' => $data['status'] ?? PostStatus::DRAFT->value,
                'content' => $data['content'] ?? null,
                'featuredImageUrl' => $data['featuredImageUrl'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create post. Please check your input.',
                ]);
            }
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Post created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/posts');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save post. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'postId' => 0,
            'title' => null,
            'status' => PostStatus::DRAFT->value,
            'content' => null,
            'slug' => null,
            'publishedAt' => null,
            'featuredImageUrl' => null,
        ]);

        $post = $this->repository->fetch($data['postId']);

        if (!$post) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save post. Please check your input.',
                ]);
            }
            return $response->withStatus(404);
        }

        if (empty($data['title']) || empty($data['content'])) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save post. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $status = PostStatus::tryFrom($data['status']);
        if ($status === null) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save post. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $post->setTitle($data['title']);
        $post->setStatus($status);
        $post->setContent($data['content']);
        $post->setFeaturedImageUrl($data['featuredImageUrl'] !== '' ? $data['featuredImageUrl'] : null);
        $post->touchModified();

        if (!empty($data['publishedAt'])) {
            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $data['publishedAt']);
            $post->setPublishedAt($parsedDate ?: null);
        } elseif ($status === PostStatus::PUBLISHED && $post->getPublishedAt() === null) {
            $post->setPublishedAt(new DateTimeImmutable());
        } else {
            $post->setPublishedAt(null);
        }

        if (!empty($data['slug'])) {
            $this->repository->updateSlug($post, $data['slug']);
        }

        $this->repository->update($post);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Post saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/posts');
    }
}
