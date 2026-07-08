<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;
use TheatreCMS\Repositories\BaseRepository;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\MenuRepository;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Repositories\UserRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Repositories\WorkRepository;

class BaseController
{
    protected const DEFAULT_PAGE_SIZE = 25;

    protected EntityManagerInterface $entityManager;
    protected PersonRepository|PostRepository|PageRepository|ProductionRepository|SeasonRepository|UserRepository|WorkRepository|VenueRepository|SponsorRepository|EventRepository|MenuRepository $repository;
    protected Twig $twig;

    public function repository(): PersonRepository|PostRepository|PageRepository|ProductionRepository|SeasonRepository|UserRepository|WorkRepository|VenueRepository|SponsorRepository|EventRepository|MenuRepository
    {
        return $this->repository;
    }

    public function store(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();

        if (empty($body)) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $data = json_decode($body, true);

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/productions');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();

        if (empty($body)) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $data = json_decode($body, true);
        $result = null;

        if (!empty($data)) {
            $result = $this->repository->create($data);
        }

        if ($result) {
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }

        $response->getBody()->write('{"error": "Failed to create item."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    public function get(Request $request, Response $response): Response
    {
        $works = $this->repository->query();
        $response->getBody()->write(json_encode($works));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? '';

        if ($id) {
            $item = $this->repository->fetch($id);

            if ($item) {
                $response->getBody()->write(json_encode($item));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write('{"error": "Item not found."}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write('{"error": "Missing item ID."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? '';

        if ($id) {
            $item = $this->repository->fetch($id);

            if ($item) {
                $this->repository->delete($item);
                return $response->withHeader('Location', '/admin/seasons');
            }

            $response->getBody()->write('{"error": "Item not found."}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write('{"error": "Missing item ID."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    protected function parseArgs(array $args, array $defaults): array
    {
        foreach ($defaults as $name => $default) {
            if (!array_key_exists($name, $args)) {
                $args[$name] = $default;
            }
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function buildPaginatedViewData(
        Request $request,
        BaseRepository $repository,
        string $itemKey,
        string $basePath,
        array $context = []
    ): array {
        $page = $this->resolveRequestedPage($request);
        $result = $repository->fetchPage($page, self::DEFAULT_PAGE_SIZE);
        $pageCount = max(1, (int) ceil($result['total'] / self::DEFAULT_PAGE_SIZE));

        if ($result['total'] === 0) {
            $page = 1;
        } elseif ($page > $pageCount) {
            $page = $pageCount;
            $result = $repository->fetchPage($page, self::DEFAULT_PAGE_SIZE);
        }

        return array_merge($context, [
            $itemKey => $result['items'],
            'pagination' => $this->buildPaginationData(
                $basePath,
                $page,
                self::DEFAULT_PAGE_SIZE,
                (int) $result['total'],
                count($result['items'])
            ),
        ]);
    }

    protected function buildListRedirect(Response $response, Request $request, string $basePath): Response
    {
        $page = $this->resolveRequestedPage($request);
        $location = $page > 1 ? sprintf('%s?page=%d', $basePath, $page) : $basePath;

        return $response->withHeader('Location', $location);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaginationData(
        string $basePath,
        int $page,
        int $perPage,
        int $total,
        int $currentCount
    ): array {
        $pageCount = max(1, (int) ceil($total / $perPage));
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? 0 : $from + $currentCount - 1;
        $windowStart = max(1, $page - 2);
        $windowEnd = min($pageCount, $windowStart + 4);
        $windowStart = max(1, $windowEnd - 4);

        return [
            'basePath' => $basePath,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pageCount' => $pageCount,
            'hasPrevious' => $page > 1,
            'hasNext' => $page < $pageCount,
            'previousPage' => max(1, $page - 1),
            'nextPage' => min($pageCount, $page + 1),
            'pages' => range($windowStart, $windowEnd),
            'from' => $from,
            'to' => $to,
        ];
    }

    private function resolveRequestedPage(Request $request): int
    {
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);

        return max(1, $page);
    }
}
