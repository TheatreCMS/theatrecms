<?php

/**
 * The person endpoint controller.
 */

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class People
{
    public function __construct(private PersonRepository $personRepository)
    {
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function create(Request $request, Response $response): Response
    {
        $args = $request->getBody()->getContents();

        if (empty($args)) {
            $response->getBody()->write('{"error": "Empty POST body."}');
            return $response->withStatus(400);
        }

        $args = json_decode($args, true);

        $data = $this->parseArgs($args, [
            'name' => '',
            'biography' => '',
            'headshotUrl' => ''
        ]);

        $person = $this->personRepository->create($data['name'], $data['biography'], $data['headshotUrl']);
        $params = json_encode($person);

        if ($params) {
            $response->getBody()->write($params);
        }

        return $response;
    }

    public function fetchAll(Request $request, Response $response): Response
    {
        $people = $this->personRepository->fetchAll();
        $data = json_encode($people);

        if ($data) {
            $response->getBody()->write($data);
        }

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string[]|float[]|int[] $args
     * @return Response
     */
    public function fetch(Request $request, Response $response, array $args = []): Response
    {
        $id = intval($args['id']);

        $person = $this->personRepository->fetch($id);

        if (is_null($person)) {
            return $response->withStatus(404);
        }

        $data = json_encode($person);

        $response->getBody()->write($data);

        return $response;
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $id = intval($args['id']);
        $args = json_decode($request->getBody()->getContents(), true);

        $args = $this->parseArgs($args, [
            'name' => '',
            'biography' => '',
            'headshotUrl' => ''
        ]);

        $person = $this->personRepository->fetch($id);

        if (is_null($person)) {
            return $response->withStatus(404);
        }

        $person->setName($args['name'])
            ->setBiography($args['biography'])
            ->setHeadshotUrl(filter_var($args['headshotUrl'], FILTER_VALIDATE_URL));

        if ($this->personRepository->save($person)) {
            $params = json_encode($person);
            $response->getBody()->write($params);
            return $response;
        }

        return $response->withStatus(200);
    }

    public function delete(Request $request, Response $response, array $args = []): Response
    {
        $id = intval($args['id']);

        $person = $this->personRepository->fetch($id);

        if (is_null($person)) {
            return $response->withStatus(404);
        }

        if ($this->personRepository->delete($person)) {
            $message = json_encode(['message' => "Person {$id} deleted."]);

            if ($message) {
                $response->getBody()->write($message);
            }

            return $response;
        }

        return $response->withStatus(500);
    }
    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function parseArgs(array $args, array $defaults): array
    {
        foreach ($defaults as $name => $default) {
            if (!array_key_exists($name, $args)) {
                $args[$name] = $default;
            }
        }

        return $args;
    }
}
