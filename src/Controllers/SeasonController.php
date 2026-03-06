<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Sponsor;
use Clubdeuce\TheatreCMS\Models\Sponsorship;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(SeasonRepository $repository, EntityManagerInterface $em)
    {
        $this->repository    = $repository;
        $this->entityManager = $em;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $season = $this->repository->create($data);
        $this->syncSponsorships($season, $data['sponsorshipSponsorIds'] ?? []);
        $this->entityManager->flush();

        return $response->withHeader('Location', '/admin/seasons');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $data = array_merge([
            'label' => null,
            'startDate' => null,
            'endDate' => null,
            'overview' => null,
            'sponsorshipSponsorIds' => [],
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
            $this->syncSponsorships($item, $data['sponsorshipSponsorIds']);
            $this->repository()->update($item);

            return $response->withHeader('Location', '/admin/seasons');
        } catch (\InvalidArgumentException $e) {
            return $response->withStatus(403)->withHeader('Location', '/admin/seasons/edit/' . $seasonId);
        }
    }

    private function syncSponsorships(Season $season, array|string $sponsorIds): void
    {
        $ids = [];

        if (is_string($sponsorIds)) {
            $ids = array_filter(array_map('trim', explode(',', $sponsorIds)), fn($value) => $value !== '');
        } elseif (is_array($sponsorIds)) {
            $ids = $sponsorIds;
        }

        $ids = array_filter($ids, fn($value) => $value !== '');
        $ids = array_map(static fn($value) => (int)$value, $ids);
        $ids = array_values(array_unique($ids));

        $existing = [];
        foreach ($season->getSponsorships() as $sponsorship) {
            $existing[$sponsorship->getSponsor()->getId()] = $sponsorship;
        }

        foreach ($existing as $sponsorId => $sponsorship) {
            if (!in_array($sponsorId, $ids, true)) {
                $season->getSponsorships()->removeElement($sponsorship);
                $this->entityManager->remove($sponsorship);
                unset($existing[$sponsorId]);
            }
        }

        $sponsorRepository = $this->entityManager->getRepository(Sponsor::class);
        foreach ($ids as $sponsorId) {
            if (isset($existing[$sponsorId])) {
                continue;
            }

            $sponsor = $sponsorRepository->find($sponsorId);
            if (!$sponsor) {
                continue;
            }

            $newSponsorship = new Sponsorship($sponsor);
            $newSponsorship->setSeason($season);
            $season->addSponsorship($newSponsorship);
            $this->entityManager->persist($newSponsorship);
        }
    }
}
