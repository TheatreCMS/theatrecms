<?php

namespace TheatreCMS\Controllers;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Services\ImageUploadService;

/**
 * @method PostRepository repository()
 */
class PostController extends BaseController
{
    private const SORTABLE_COLUMNS = ['title', 'publishedAt'];

    private ImageUploadService $imageUploadService;

    public function __construct(PostRepository $repository, EntityManagerInterface $em, Twig $twig, ImageUploadService $imageUploadService)
    {
        $this->repository = $repository;
        $this->entityManager = $em;
        $this->twig       = $twig;
        $this->imageUploadService = $imageUploadService;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        [$search, $sort, $direction] = $this->resolveListQuery($request, self::SORTABLE_COLUMNS);
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'posts',
            '/admin/posts',
            ['statuses' => ContentStatus::labels()],
            $search,
            $sort,
            $direction
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/posts/_list.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/posts/index.html.twig', $data);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/posts/create.html.twig', [
            'statuses' => ContentStatus::labels(),
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
            'statuses' => ContentStatus::labels(),
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
                'statuses' => ContentStatus::labels(),
                'status_labels' => ContentStatus::labels(),
            ]
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/posts/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/posts');
    }

    public function removeFeaturedImage(Request $request, Response $response, array $args = []): Response
    {
        $post = $this->repository->fetch($args['id']);

        if (is_null($post)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Post not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        $this->imageUploadService->delete($post->getFeaturedImageUrl());
        $post->setFeaturedImageUrl(null);
        $this->repository->update($post);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/posts/_featured_image_removed.html.twig', [
                'post' => $post,
            ]);
        }

        return $response->withHeader('Location', '/admin/posts/edit/' . $post->getId());
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
            $post = $this->repository->create([
                'title' => $data['title'] ?? null,
                'status' => $data['status'] ?? ContentStatus::DRAFT->value,
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

        $editUrl = '/admin/posts/edit/' . $post->getId();

        if ($request->getHeaderLine('HX-Request')) {
            return $response->withHeader('HX-Redirect', $editUrl);
        }

        return $response->withHeader('Location', $editUrl);
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
            'status' => ContentStatus::DRAFT->value,
            'content' => null,
            'slug' => null,
            'publishedAt' => null,
            'featuredImageUrl' => null,
        ]);

        $featuredImageUploadUrl = $this->storeFeaturedImageUpload($request);
        if ($featuredImageUploadUrl !== null) {
            $data['featuredImageUrl'] = $featuredImageUploadUrl;
        }

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

        $status = ContentStatus::tryFrom($data['status']);
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
        } elseif ($status === ContentStatus::PUBLISHED && $post->getPublishedAt() === null) {
            $post->setPublishedAt(new DateTimeImmutable());
        } else {
            $post->setPublishedAt(null);
        }

        if (!empty($data['slug'])) {
            $this->repository->updateSlug($post, $data['slug']);
        }

        $this->repository->update($post);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/posts/_saved.html.twig', [
                'post' => $post,
            ]);
        }

        return $response->withHeader('Location', '/admin/posts');
    }

    private function storeFeaturedImageUpload(Request $request): ?string
    {
        $uploadedFiles = $request->getUploadedFiles();
        $featuredImage = $uploadedFiles['featuredImage'] ?? null;

        if (!$featuredImage instanceof UploadedFileInterface || $featuredImage->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($featuredImage->getError() !== UPLOAD_ERR_OK || !$this->imageUploadService->isImage($featuredImage)) {
            return null;
        }

        return $this->imageUploadService->store($featuredImage);
    }
}
