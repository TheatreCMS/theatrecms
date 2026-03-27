<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TheatreCMS\Repositories\PageRepository;

/**
 * @method PageRepository repository()
 */
class PageController extends BaseController
{
    public function __construct(PageRepository $repository, EntityManagerInterface $em)
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
                'content' => $data['content'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        return $response->withHeader('Location', '/admin/pages');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'pageId' => 0,
            'title' => null,
            'content' => null,
            'slug' => null,
        ]);

        $page = $this->repository->fetch((int) $data['pageId']);

        if (!$page) {
            return $response->withStatus(404);
        }

        if (empty($data['title']) || empty($data['content'])) {
            return $response->withStatus(400);
        }

        $page->setTitle($data['title']);
        $page->setContent($data['content']);
        $page->touchModified();

        if (!empty($data['slug'])) {
            $this->repository->updateSlug($page, $data['slug']);
        }

        $this->repository->update($page);

        return $response->withHeader('Location', '/admin/pages');
    }
}
