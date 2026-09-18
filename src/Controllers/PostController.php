<?php

namespace TheatreCMS\Controllers;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Traits\HandlesContentImages;

/**
 * @extends BaseController<PostRepository>
 */
class PostController extends BaseController
{
    use HandlesContentImages;

    private const SORTABLE_COLUMNS = ['title', 'publishedAt'];

    public function __construct(
        PostRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        private readonly ContentMetaRepository $contentMeta
    ) {
        parent::__construct($repository, $twig, $em);
    }

    protected function contentType(): string
    {
        return 'post';
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
            'heroImageId' => $this->contentMeta->get($this->contentType(), $post->getId(), self::HERO_IMAGE_META_KEY),
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
            ]);
            $this->applyFeaturedImage($post, $data['featuredImageId'] ?? null);
            $this->applyHeroImage($post->getId(), $data['heroImageId'] ?? null);
            $this->entityManager->flush();
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
            'featuredImageId' => null,
            'heroImageId' => null,
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
        $this->applyFeaturedImage($post, $data['featuredImageId']);
        $this->applyHeroImage($post->getId(), $data['heroImageId']);
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
}
