<?php

namespace Clubdeuce\TheatreCMS\Controllers;

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

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/seasons');
    }
}
