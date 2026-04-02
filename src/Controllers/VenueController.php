<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Repositories\VenueRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class VenueController extends BaseController
{
    public function __construct(VenueRepository $repository, Twig $twig)
    {
        $this->repository = $repository;
        $this->twig       = $twig;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/venues/index.html.twig', [
            'venues' => $this->repository->fetchAll(),
        ]);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/venues/create.html.twig');
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/venues/edit.html.twig', [
            'venue' => $this->repository->fetch($args['id']),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $venue = $this->repository->fetch(intval($args['id']));
        if ($venue) {
            $this->repository->delete($venue);
        }

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/venues/_table.html.twig', [
                'venues' => $this->repository->fetchAll(),
            ]);
        }

        return $response->withHeader('Location', '/admin/venues');
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create venue. Please check your input.',
                ]);
            }
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

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Venue created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/venues');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save venue. Please check your input.',
                ]);
            }
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
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Venue not found.',
                ]);
            }
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

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Venue saved successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/venues');
    }
}
