<?php

/**
 * The person endpoint controller.
 */

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Class People
 * @package Clubdeuce\TheatreCMS\Controllers
 *
 * @property PersonRepository $repository
 */
class People extends BaseController
{
    public function __construct(PersonRepository $repository)
    {
        $this->repository = $repository;
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

        $person = $this->repository->fetch($id);

        if (is_null($person)) {
            return $response->withStatus(404);
        }

        $person->setName($args['name'])
            ->setFirstName($args['firstName'])
            ->setLastName($args['lastName'])
            ->setBiography($args['biography'])
            ->setHeadshotUrl(filter_var($args['headshotUrl'], FILTER_VALIDATE_URL));

        $this->repository->update($person);
        $params = json_encode($person);
        $response->getBody()->write($params);
        return $response;

    }
}
