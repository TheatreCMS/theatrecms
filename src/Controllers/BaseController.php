<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\BaseRepository as BaseRepository;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class BaseController
{

    protected BaseRepository $repository;

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

        if ($id)
        {
            $item = $this->repository->fetch($id);

            if ($item)
            {
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

        if ($id)
        {
            $item = $this->repository->fetch($id);

            if ($item)
            {
                $this->repository->delete($item);
                return $response->withStatus(204);
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
}
