<?php

namespace TheatreCMS\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;
use TheatreCMS\Repositories\PaginatedRepositoryInterface;

/**
 * @template TRepository of PaginatedRepositoryInterface
 */
class BaseController
{
    protected const DEFAULT_PAGE_SIZE = 25;

    protected EntityManagerInterface $entityManager;

    /** @var TRepository */
    protected PaginatedRepositoryInterface $repository;

    protected Twig $twig;

    /**
     * @param TRepository $repository
     */
    public function __construct(
        PaginatedRepositoryInterface $repository,
        Twig $twig,
        ?EntityManagerInterface $entityManager = null
    ) {
        $this->repository = $repository;
        $this->twig = $twig;

        if ($entityManager !== null) {
            $this->entityManager = $entityManager;
        }
    }

    /**
     * @return TRepository
     */
    public function repository(): PaginatedRepositoryInterface
    {
        return $this->repository;
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
     * Read and whitelist the `q` (search), `sort`, and `direction` query params shared by
     * every filterable/sortable admin list view.
     *
     * @param string[] $sortableColumns
     * @return array{0: string, 1: string, 2: string} [search, sort, direction]
     */
    protected function resolveListQuery(Request $request, array $sortableColumns): array
    {
        $queryParams = $request->getQueryParams();
        $search = trim((string) ($queryParams['q'] ?? ''));

        $sort = (string) ($queryParams['sort'] ?? '');
        if (!in_array($sort, $sortableColumns, true)) {
            $sort = '';
        }

        $direction = strtolower((string) ($queryParams['direction'] ?? 'asc'));
        if ($direction !== 'desc') {
            $direction = 'asc';
        }

        return [$search, $sort, $direction];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function buildPaginatedViewData(
        Request $request,
        PaginatedRepositoryInterface $repository,
        string $itemKey,
        string $basePath,
        array $context = [],
        string $search = '',
        string $sort = '',
        string $direction = 'asc',
        array $criteria = []
    ): array {
        $page = $this->resolveRequestedPage($request);
        $result = $repository->fetchPage($page, self::DEFAULT_PAGE_SIZE, $search, $sort, $direction, $criteria);
        $pageCount = max(1, (int) ceil($result['total'] / self::DEFAULT_PAGE_SIZE));

        if ($result['total'] === 0) {
            $page = 1;
        } elseif ($page > $pageCount) {
            $page = $pageCount;
            $result = $repository->fetchPage($page, self::DEFAULT_PAGE_SIZE, $search, $sort, $direction, $criteria);
        }

        return array_merge($context, [
            $itemKey => $result['items'],
            'pagination' => $this->buildPaginationData(
                $basePath,
                $page,
                self::DEFAULT_PAGE_SIZE,
                (int) $result['total'],
                count($result['items']),
                $search,
                $sort,
                $direction,
                $criteria
            ),
        ]);
    }

    protected function buildListRedirect(Response $response, Request $request, string $basePath): Response
    {
        $page = $this->resolveRequestedPage($request);
        $location = $page > 1 ? sprintf('%s?page=%d', $basePath, $page) : $basePath;

        return $response->withHeader('Location', $location);
    }

    protected function freshAlertResponse(Response $response, string $type, string $message): Response
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->twig->fetch('admin/partials/_alert.html.twig', [
            'type'    => $type,
            'message' => $message,
        ]));
        rewind($stream);
        return $response->withBody(new \Slim\Psr7\Stream($stream));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaginationData(
        string $basePath,
        int $page,
        int $perPage,
        int $total,
        int $currentCount,
        string $search = '',
        string $sort = '',
        string $direction = 'asc',
        array $extra = []
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
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'extra' => $extra,
        ];
    }

    private function resolveRequestedPage(Request $request): int
    {
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);

        return max(1, $page);
    }
}
