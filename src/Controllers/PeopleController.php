<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PeopleController extends BaseController
{
    public function __construct(PersonRepository $repository)
    {
        $this->repository = $repository;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        // Ensure required fields
        $data = $this->parseArgs($data, [
            'firstName' => null,
            'lastName'  => null,
            'biography' => null,
            'headshotUrl' => null,
        ]);

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/people');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'personId' => 0,
            'firstName' => null,
            'lastName' => null,
            'biography' => null,
            'headshotUrl' => null,
        ]);

        $person = $this->repository->fetch(intval($data['personId']));

        if (is_null($person)) {
            return $response->withStatus(404);
        }

        $person->setFirstName($data['firstName'])
            ->setLastName($data['lastName'])
            ->setBiography($data['biography'])
            ->setHeadshotUrl(filter_var($data['headshotUrl'], FILTER_VALIDATE_URL) ?: $data['headshotUrl']);

        $this->repository->update($person);

        return $response->withHeader('Location', '/admin/people');
    }
}

