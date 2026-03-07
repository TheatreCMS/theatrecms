<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Models\Work;
use TheatreCMS\Repositories\WorkRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WorksController extends BaseController
{
    public function __construct(WorkRepository $repository)
    {
        $this->repository = $repository;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        if (($creatorEntries = $this->collectCreatorEntries($data)) !== null) {
            $data['creators'] = $creatorEntries;
        }

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/works');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $data = array_merge([
            'id' => null,
            'title' => null,
            'description' => null,
            'synopsis' => null,
            'creators' => null,
        ], $data);

        $workId = $data['id'] ?? null;

        if (!$workId) {
            return $response->withStatus(400);
        }

        $work = $this->repository->fetch(intval($workId));

        if (!$work) {
            return $response->withStatus(404);
        }

        if (($creatorEntries = $this->collectCreatorEntries($data)) !== null) {
            $data['creators'] = $creatorEntries;
        }

        // Help static analyzer: create a typed local variable
        /** @var Work $workEntity */
        $workEntity = $work;

        // Update the entity using repository helper
        $this->repository->updateFromArgs($workEntity, $data);

        return $response->withHeader('Location', '/admin/works');
    }

    private function collectCreatorEntries(array $data): ?array
    {
        if (array_key_exists('creatorIds', $data) && is_array($data['creatorIds'])) {
            $roles = $data['creatorRoles'] ?? [];
            return $this->normalizeCreatorRows($data['creatorIds'], $roles);
        }

        if (!empty($data['creators']) && is_array($data['creators'])) {
            $roles = $data['creators_roles'] ?? [];
            return $this->normalizeLegacyCreatorEntries($data['creators'], $roles);
        }

        return null;
    }

    private function normalizeCreatorRows(array $ids, array $roles): array
    {
        $entries = [];

        foreach ($ids as $index => $rawId) {
            $creatorId = intval($rawId);
            if (!$creatorId) {
                continue;
            }

            $role = '';
            if (isset($roles[$index])) {
                $role = trim(strval($roles[$index]));
            }

            $entries[] = ['id' => $creatorId, 'role' => $role];
        }

        return $entries;
    }

    private function normalizeLegacyCreatorEntries(array $creatorIds, array $roles): array
    {
        $entries = [];

        foreach ($creatorIds as $rawId) {
            $creatorId = intval($rawId);
            if (!$creatorId) {
                continue;
            }

            $role = '';
            if (isset($roles[$creatorId])) {
                $role = trim(strval($roles[$creatorId]));
            }

            $entries[] = ['id' => $creatorId, 'role' => $role];
        }

        return $entries;
    }
}
