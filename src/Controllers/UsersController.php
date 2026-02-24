<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;


class UsersController extends BaseController
{
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function store(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body))
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);

        $required = ['email', 'username', 'password'];

        $data = $this->parseArgs($body,[
            'email'    => null,
            'username' => null,
            'password' => null,
            'role'     => 'user',
        ]);

        foreach($required as $index) {
            if (empty($data[$index])) {
                throw new \InvalidArgumentException("$index is required");
            }
        }

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/users');
    }

}
