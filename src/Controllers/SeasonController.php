<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Clubdeuce\TheatreCMS\Models\Season;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class SeasonController
 * @package Clubdeuce\TheatreCMS\Controllers
 *
 * @method SeasonRepository repository()
 */
class SeasonController extends BaseController
{
    public function __construct(SeasonRepository $repository)
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

        return $response->withHeader('Location', '/admin/seasons')->withStatus(201);
    }

    public function update(Request $request, Response $response): Response
    {
        $data= $request->getParsedBody();

        $data = array_merge([
            'label' => null,
            'startDate' => null,
            'endDate' => null,
            'overview' => null,
        ], $data);

        $seasonId = $data['seasonId'] ?? null;

        if (!$seasonId)
            return $response->withStatus(400);

        try{
            $start = new \DateTime($data['startDate']);
            $end   = new \DateTime($data['endDate']);

            /** @var Season $item */
            $item = $this->repository()->fetch($seasonId);
            $item->setLabel($data['label']);
            $item->setStartDate($start);
            $item->setEndDate($end);
            $item->setOverview($data['overview']);
            $this->repository()->update($item);

            return $response->withHeader('Location', '/admin/seasons')->withStatus(201);
        } catch (\DateMalformedStringException $e) {
            return $response->withStatus(403)->withHeader('Location', '/admin/seasons/edit/' . $seasonId);
        }

    }
}
