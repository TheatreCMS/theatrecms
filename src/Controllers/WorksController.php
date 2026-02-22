<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Models\Work;
use Clubdeuce\TheatreCMS\Repositories\WorkRepository;
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

        // Normalize creators and roles into structured entries
        if (!empty($data['creators']) && is_array($data['creators'])) {
            $roles = $data['creators_roles'] ?? [];
            $structured = [];
            foreach ($data['creators'] as $id) {
                $id = intval($id);
                if (!$id) {
                    continue;
                }
                $role = '';
                if (isset($roles[$id])) {
                    $role = trim(strval($roles[$id]));
                }
                $structured[] = ['id' => $id, 'role' => $role];
            }
            $data['creators'] = $structured;
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

        // Normalize creators and roles into structured entries if provided
        if (!empty($data['creators']) && is_array($data['creators'])) {
            $roles = $data['creators_roles'] ?? [];
            $structured = [];
            foreach ($data['creators'] as $id) {
                $id = intval($id);
                if (!$id) {
                    continue;
                }
                $role = '';
                if (isset($roles[$id])) {
                    $role = trim(strval($roles[$id]));
                }
                $structured[] = ['id' => $id, 'role' => $role];
            }
            $data['creators'] = $structured;
        }

        // Help static analyzer: create a typed local variable
        /** @var Work $workEntity */
        $workEntity = $work;

        // Update the entity using repository helper
        $this->repository->updateFromArgs($workEntity, $data);

        return $response->withHeader('Location', '/admin/works');
    }
}
