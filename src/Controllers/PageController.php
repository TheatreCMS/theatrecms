<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Repositories\PageRepository;

/**
 * @method PageRepository repository()
 */
class PageController extends BaseController
{
    public function __construct(PageRepository $repository, EntityManagerInterface $em, Twig $twig)
    {
        $this->repository = $repository;
        $this->entityManager = $em;
        $this->twig       = $twig;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/pages/index.html.twig',
            $this->buildPaginatedViewData($request, $this->repository, 'pages', '/admin/pages')
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/pages/create.html.twig');
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $page = $this->repository->fetch($args['id']);

        if (!$page) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/pages/edit.html.twig', [
            'page' => $page,
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $page = $this->repository->fetch($args['id']);
        if ($page) {
            try {
                $this->repository->delete($page);
            } catch (\Exception $e) {
                trigger_error("Unable to delete page: {$e->getMessage()}");
            }
        }

        $data = $this->buildPaginatedViewData($request, $this->repository, 'pages', '/admin/pages');

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/pages/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/pages');
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $isHtmx = (bool) $request->getHeaderLine('HX-Request');
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($isHtmx) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'error', 'message' => 'No data received.',
                ]);
            }
            return $response->withStatus(400);
        }

        try {
            $this->repository->create([
                'title'   => $data['title'] ?? null,
                'content' => $data['content'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            if ($isHtmx) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'error', 'message' => $e->getMessage(),
                ]);
            }
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        if ($isHtmx) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type' => 'success', 'message' => 'Page created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/pages');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $isHtmx = (bool) $request->getHeaderLine('HX-Request');
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($isHtmx) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'error', 'message' => 'No data received.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'pageId' => 0, 'title' => null, 'content' => null, 'slug' => null,
        ]);

        $page = $this->repository->fetch((int) $data['pageId']);

        if (!$page) {
            if ($isHtmx) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'error', 'message' => 'Page not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        if (empty($data['title']) || empty($data['content'])) {
            if ($isHtmx) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'error', 'message' => 'Title and content are required.',
                ]);
            }
            return $response->withStatus(400);
        }

        $page->setTitle($data['title']);
        $page->setContent($data['content']);
        $page->touchModified();

        if (!empty($data['slug'])) {
            $this->repository->updateSlug($page, $data['slug']);
        }

        $this->repository->update($page);

        if ($isHtmx) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type' => 'success', 'message' => 'Page saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/pages');
    }
}
