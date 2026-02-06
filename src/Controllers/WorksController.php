<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\WorkRepository;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class WorksController
{
    public function __construct(private readonly WorkRepository $repository)
    {
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

        if ($id)
        {
            $work = $this->repository->fetch($id);

            if ($work)
            {
                $response->getBody()->write(json_encode($work));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write('{"error": "Work not found."}');
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write('{"error": "Missing work ID."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();

        if (empty($body))
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);

        $data = json_decode($body, true);
        $result = null;

        if (!empty($data))
            $result = $this->repository->create($data);

        if ($result) {
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }

        $response->getBody()->write('{"error": "Failed to create work."}');
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
}
