<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\SponsorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SponsorController extends BaseController
{
    protected SponsorRepository $sponsorRepository;

    public function __construct(SponsorRepository $repository)
    {
        $this->sponsorRepository = $repository;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'name' => null,
            'logoUrl' => null,
            'websiteUrl' => null,
        ]);

        $this->sponsorRepository->create($data);

        return $response->withHeader('Location', '/admin/sponsors');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'sponsorId' => 0,
            'name' => null,
            'logoUrl' => null,
            'websiteUrl' => null,
        ]);

        $sponsor = $this->sponsorRepository->fetch(intval($data['sponsorId']));

        if (is_null($sponsor)) {
            return $response->withStatus(404);
        }

        $sponsor->setName($data['name'])
            ->setLogoUrl($data['logoUrl'])
            ->setWebsiteUrl($data['websiteUrl']);

        $this->sponsorRepository->update($sponsor);

        return $response->withHeader('Location', '/admin/sponsors');
    }
}
