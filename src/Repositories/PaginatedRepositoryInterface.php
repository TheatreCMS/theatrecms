<?php

namespace TheatreCMS\Repositories;

interface PaginatedRepositoryInterface
{
    /**
     * @return array{items: array<int, object>, total: int, page: int, perPage: int}
     */
    public function fetchPage(
        int $page = 1,
        int $perPage = 25,
        string $search = '',
        string $sort = '',
        string $direction = 'asc'
    ): array;
}
