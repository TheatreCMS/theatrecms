<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\VenueRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VenueController extends BaseController
{
    public function __construct(VenueRepository $repository)
    {
        $this->repository = $repository;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'name' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            'capacity' => null,
            'description' => null,
            'accessibilityInfo' => null,
            'websiteUrl' => null,
            'mapUrl' => null,
        ]);

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/venues');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'venueId' => 0,
            'name' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            'capacity' => null,
            'description' => null,
            'accessibilityInfo' => null,
            'websiteUrl' => null,
            'mapUrl' => null,
        ]);

        $venue = $this->repository->fetch(intval($data['venueId']));

        if (is_null($venue)) {
            return $response->withStatus(404);
        }

        $venue->setName($data['name'])
            ->setAddress($data['address'])
            ->setCity($data['city'])
            ->setState($data['state'])
            ->setPostcode($data['postcode'])
            ->setCapacity(is_numeric($data['capacity']) ? intval($data['capacity']) : null)
            ->setDescription($data['description'])
            ->setAccessibilityInfo($data['accessibilityInfo'])
            ->setWebsiteUrl($data['websiteUrl'])
            ->setMapUrl($data['mapUrl']);

        $this->repository->update($venue);

        return $response->withHeader('Location', '/admin/venues');
    }
}
