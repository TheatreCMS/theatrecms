<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Models\Image;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Sponsorship;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Class SeasonController
 * @package TheatreCMS\Controllers
 *
 * @method SeasonRepository repository()
 */
class SeasonController extends BaseController
{
    private SponsorRepository $sponsorRepo;

    public function __construct(
        SeasonRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        SponsorRepository $sponsorRepo
    ) {
        $this->repository    = $repository;
        $this->entityManager = $em;
        $this->twig          = $twig;
        $this->sponsorRepo   = $sponsorRepo;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/seasons/index.html.twig',
            $this->buildPaginatedViewData($request, $this->repository(), 'seasons', '/admin/seasons')
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/seasons/create.html.twig', [
            'sponsors' => $this->sponsorRepo->fetchAll(),
        ]);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/seasons/edit.html.twig', [
            'season'   => $this->repository()->fetch($args['id']),
            'sponsors' => $this->sponsorRepo->fetchAll(),
        ]);
    }

    public function show(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'seasons/show.html.twig', [
            'season' => $this->repository()->fetch($args['id']),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $season = $this->repository()->fetch($args['id']);
        try {
            if ($season) {
                $this->repository()->delete($season);
            }
        } catch (\Exception $e) {
            trigger_error("Unable to delete season: {$e->getMessage()}");
        }

        $data = $this->buildPaginatedViewData($request, $this->repository(), 'seasons', '/admin/seasons');

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/seasons/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/seasons');
    }

    public function removeFeaturedImage(Request $request, Response $response, array $args = []): Response
    {
        $season = $this->repository()->fetch($args['id']);

        if (is_null($season)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Season not found.',
                ]);
            }
            return $response->withStatus(404);
        }

        $season->setFeaturedImage(null);
        $this->repository()->update($season);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_featured_image_field.html.twig', [
                'entityType'       => 'season',
                'entityId'         => $season->getId(),
                'featuredImageUrl' => $season->getFeaturedImageUrl(),
                'featuredImageId'  => null,
            ]);
        }

        return $response->withHeader('Location', '/admin/seasons/edit/' . $season->getId());
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create season. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $season = $this->repository->create($data);
        $this->applyFeaturedImage($season, $data['featuredImageId'] ?? null);
        $this->syncSponsorships($season, $data['sponsorshipSponsorIds'] ?? []);
        $this->entityManager->flush();

        $editUrl = '/admin/seasons/edit/' . $season->getId();

        if ($request->getHeaderLine('HX-Request')) {
            return $response->withHeader('HX-Redirect', $editUrl);
        }

        return $response->withHeader('Location', $editUrl);
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        $data = array_merge([
            'label' => null,
            'startDate' => null,
            'endDate' => null,
            'overview' => null,
            'featuredImageId' => null,
            'sponsorshipSponsorIds' => [],
        ], $data);

        $seasonId = $data['seasonId'] ?? null;

        if (!$seasonId) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save season. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        try {
            $start = new \DateTime($data['startDate']);
            $end   = new \DateTime($data['endDate']);

            /** @var Season $item */
            $item = $this->repository()->fetch($seasonId);
            $item->setLabel($data['label']);
            $item->setStartDate($start);
            $item->setEndDate($end);
            $item->setOverview($data['overview']);
            $this->applyFeaturedImage($item, $data['featuredImageId']);
            $this->syncSponsorships($item, $data['sponsorshipSponsorIds']);
            $this->repository()->update($item);

            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/seasons/_saved.html.twig', [
                    'season' => $item,
                ]);
            }

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

    private function applyFeaturedImage(Season $season, mixed $featuredImageId): void
    {
        if (empty($featuredImageId)) {
            $season->setFeaturedImage(null);
            return;
        }

        $image = $this->entityManager->getRepository(Image::class)->find((int) $featuredImageId);
        $season->setFeaturedImage($image);
    }
}
