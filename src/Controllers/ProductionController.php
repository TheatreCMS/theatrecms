<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Sponsorship;
use TheatreCMS\Models\Work;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\RoleType;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Repositories\WorkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class ProductionController
 * @package TheatreCMS\Controllers
 *
 * @method ProductionRepository repository()
 */
class ProductionController extends BaseController
{
    private const UPLOADS_SUBPATH = '/uploads/';

    private SeasonRepository $seasonRepo;
    private PersonRepository $personRepo;
    private WorkRepository $worksRepo;
    private SponsorRepository $sponsorRepo;
    private VenueRepository $venueRepo;
    private EventRepository $eventRepo;

    public function __construct(
        ProductionRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        SeasonRepository $seasonRepo,
        PersonRepository $personRepo,
        WorkRepository $worksRepo,
        SponsorRepository $sponsorRepo,
        VenueRepository $venueRepo,
        EventRepository $eventRepo
    ) {
        $this->repository    = $repository;
        $this->entityManager = $em;
        $this->twig          = $twig;
        $this->seasonRepo    = $seasonRepo;
        $this->personRepo    = $personRepo;
        $this->worksRepo     = $worksRepo;
        $this->sponsorRepo   = $sponsorRepo;
        $this->venueRepo     = $venueRepo;
        $this->eventRepo     = $eventRepo;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render(
            $response,
            'admin/productions/index.html.twig',
            $this->buildPaginatedViewData($request, $this->repository, 'productions', '/admin/productions')
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/productions/create.html.twig', [
            'seasons'  => $this->seasonRepo->fetchAll(),
            'people'   => $this->personRepo->fetchAll(),
            'works'    => $this->worksRepo->fetchAll(),
            'sponsors' => $this->sponsorRepo->fetchAll(),
            'venues'   => $this->venueRepo->fetchAll(),
        ]);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        /** @var \TheatreCMS\Models\Production $production */
        $production = $this->repository->fetch($args['id']);
        $queryParams = $request->getQueryParams();
        $activeTab = ($queryParams['tab'] ?? '') === 'performances' ? 'performances' : 'details';

        return $this->twig->render($response, 'admin/productions/edit.html.twig', [
            'production' => $production,
            'seasons'    => $this->seasonRepo->fetchAll(),
            'people'     => $this->personRepo->fetchAll(),
            'works'      => $this->worksRepo->fetchAll(),
            'performers'      => $production->getPerformers()->toArray(),
            'productionTeam'  => $production->getProductionTeam()->toArray(),
            'sponsors'   => $this->sponsorRepo->fetchAll(),
            'venues'     => $this->venueRepo->fetchAll(),
            'events'     => $this->eventRepo->fetchByProduction((int) $args['id']),
            'activeTab'  => $activeTab,
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        $production = $this->repository->fetch($args['id']);
        $this->repository->delete($production);

        $data = $this->buildPaginatedViewData($request, $this->repository, 'productions', '/admin/productions');

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/productions/_list.html.twig', $data);
        }

        return $this->buildListRedirect($response, $request, '/admin/productions');
    }

    public function removeFeaturedImage(Request $request, Response $response, array $args = []): Response
    {
        /** @var Production|null $production */
        $production = $this->repository->fetch((int) $args['id']);

        if ($production === null) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Production not found.',
                ]);
            }

            return $response->withStatus(404);
        }

        $this->deleteFeaturedImageFile($production->getFeaturedImageUrl());
        $production->setFeaturedImageUrl(null);
        $this->repository->update($production);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/productions/_featured_image_removed.html.twig', [
                'production' => $production,
            ]);
        }

        return $response->withHeader('Location', '/admin/productions/edit/' . $production->getId());
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to create production. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'sponsorshipSponsorIds' => [],
            'venueId' => null,
            'creativeIds' => [],
            'creativeRoles' => [],
            'performerIds' => [],
            'performerRoles' => [],
        ]);

        $production = $this->repository->create($data);
        $this->handleFeaturedImageUpload($request, $production);

        $personRepository = $this->entityManager->getRepository(Person::class);

        $attachPeople = function (array $ids, array $roles, callable $add) use ($personRepository) {
            foreach ($ids as $idx => $personId) {
                if (empty($personId)) {
                    continue;
                }

                $person = $personRepository->find($personId);
                if (!$person) {
                    continue;
                }

                $add($person, $roles[$idx] ?? null);
            }
        };

        $attachPeople(
            is_array($data['creativeIds']) ? $data['creativeIds'] : [],
            is_array($data['creativeRoles']) ? $data['creativeRoles'] : [],
            fn (Person $person, ?string $role) => $production->addToCreativeTeam($person, $role)
        );

        $attachPeople(
            is_array($data['performerIds']) ? $data['performerIds'] : [],
            is_array($data['performerRoles']) ? $data['performerRoles'] : [],
            fn (Person $person, ?string $role) => $production->addPerformer($person, $role)
        );

        $this->syncSponsorships($production, $data['sponsorshipSponsorIds']);
        $this->entityManager->flush();

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                'type'    => 'success',
                'message' => 'Production created successfully.',
            ]);
        }

        return $response->withHeader('Location', '/admin/productions');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save production. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'productionId' => 0,
            'name' => null,
            'seasonId' => null,
            'opening' => null,
            'closing' => null,
            'description' => null,
            'promoVideoUrl' => null,
            'ticketPurchaseUrl' => null,
            'works' => [],
            'people' => [],
            'creativeIds' => [],
            'creativeRoles' => [],
            'performerIds' => [],
            'performerRoles' => [],
            'productionTeamIds' => [],
            'productionTeamRoles' => [],
            'sponsorshipSponsorIds' => [],
            'venueId' => null,
        ]);

        /**
         * @var Production $item
         */
        $item = $this->repository->fetch($data['productionId']);
        $season = $this->entityManager->getRepository(Season::class)->find($data['seasonId']);
        $venue = null;
        if (!empty($data['venueId'])) {
            $venue = $this->entityManager->getRepository(Venue::class)->find((int)$data['venueId']);
            if (!$venue) {
                if ($request->getHeaderLine('HX-Request')) {
                    return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type'    => 'error',
                        'message' => 'Unable to save production. Please check your input.',
                    ]);
                }
                return $response->withStatus(400);
            }
        }
        $worksRepository = $this->entityManager->getRepository(Work::class);

        $workIds = $data['works'];
        if (!is_array($workIds)) {
            $workIds = !empty($workIds) ? explode(',', (string) $workIds) : [];
        }

        $workIds = array_filter(array_map('trim', array_map('strval', $workIds)), static function (string $workId): bool {
            return $workId !== '';
        });

        $works = [];
        foreach ($workIds as $workId) {
            $work = $worksRepository->find((int) $workId);
            if ($work instanceof Work) {
                $works[] = $work;
            }
        }

        try {
            $opening = new \DateTime($data['opening']);
            $closing = new \DateTime($data['closing']);
        } catch (\Exception $e) {
            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type'    => 'error',
                    'message' => 'Unable to save production. Please check your input.',
                ]);
            }
            return $response->withStatus(400);
        }

        $item
            ->setName($data['name'])
            ->setSeason($season)
            ->setVenue($venue)
            ->setOpening($opening)
            ->setClosing($closing)
            ->setDescription($data['description'])
            ->setPromoVideoUrl($data['promoVideoUrl'])
            ->setTicketPurchaseUrl($data['ticketPurchaseUrl'])
            ->setWorks($works);

        $this->handleFeaturedImageUpload($request, $item);

        $creativeIds = is_array($data['creativeIds']) ? $data['creativeIds'] : [];
        $creativeRoles = is_array($data['creativeRoles']) ? $data['creativeRoles'] : [];
        $performerIds = is_array($data['performerIds']) ? $data['performerIds'] : [];
        $performerRoles = is_array($data['performerRoles']) ? $data['performerRoles'] : [];
        $productionTeamIds = is_array($data['productionTeamIds']) ? $data['productionTeamIds'] : [];
        $productionTeamRoles = is_array($data['productionTeamRoles']) ? $data['productionTeamRoles'] : [];

        $existingCreatives = [];
        $existingPerformers = [];
        $existingProductionTeam = [];

        foreach ($item->getPeople() as $productionPerson) {
            $personId = $productionPerson->getPerson()->getId();
            $roleType = $productionPerson->getRoleType();

            if ($roleType === RoleType::Creative) {
                $existingCreatives[$personId] = $productionPerson;
            } elseif ($roleType === RoleType::Cast) {
                $existingPerformers[$personId] = $productionPerson;
            } elseif ($roleType === RoleType::ProductionTeam) {
                $existingProductionTeam[$personId] = $productionPerson;
            }
        }

        $personRepository = $this->entityManager->getRepository(Person::class);

        foreach ($creativeIds as $idx => $creativeId) {
            if (empty($creativeId)) {
                continue;
            }

            $role = $creativeRoles[$idx] ?? null;

            if (isset($existingCreatives[$creativeId])) {
                $existingCreatives[$creativeId]->setRole($role);
                unset($existingCreatives[$creativeId]);
                continue;
            }

            $person = $personRepository->find($creativeId);
            if (!$person) {
                continue;
            }

            $item->addToCreativeTeam($person, $role);
        }

        foreach ($performerIds as $idx => $performerId) {
            if (empty($performerId)) {
                continue;
            }

            $role = $performerRoles[$idx] ?? null;

            if (isset($existingPerformers[$performerId])) {
                $existingPerformers[$performerId]->setRole($role);
                unset($existingPerformers[$performerId]);
                continue;
            }

            $person = $personRepository->find($performerId);
            if (!$person) {
                continue;
            }

            $item->addPerformer($person, $role);
        }

        foreach ($productionTeamIds as $idx => $memberId) {
            if (empty($memberId)) {
                continue;
            }

            $role = $productionTeamRoles[$idx] ?? null;

            if (isset($existingProductionTeam[$memberId])) {
                $existingProductionTeam[$memberId]->setRole($role);
                unset($existingProductionTeam[$memberId]);
                continue;
            }

            $person = $personRepository->find($memberId);
            if (!$person) {
                continue;
            }

            $item->addToProductionTeam($person, $role);
        }

        foreach ($existingCreatives as $staleCreative) {
            $item->getPeople()->removeElement($staleCreative);
            $this->entityManager->remove($staleCreative);
        }

        foreach ($existingPerformers as $stalePerformer) {
            $item->getPeople()->removeElement($stalePerformer);
            $this->entityManager->remove($stalePerformer);
        }

        foreach ($existingProductionTeam as $staleMember) {
            $item->getPeople()->removeElement($staleMember);
            $this->entityManager->remove($staleMember);
        }

        $this->syncSponsorships($item, $data['sponsorshipSponsorIds']);

        $this->repository->update($item);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/productions/_saved.html.twig', [
                'production' => $item,
            ]);
        }

        return $response->withHeader('Location', '/admin/productions');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('name', new NotBlank());
        $metadata->addPropertyConstraint('opening', new Date());
        $metadata->addPropertyConstraint('closing', new Date());
    }

    private function syncSponsorships(Production $production, array|string $sponsorIds): void
    {
        $ids = [];

        if (is_string($sponsorIds)) {
            $ids = array_filter(array_map('trim', explode(',', $sponsorIds)), fn($value) => $value !== '');
        } elseif (is_array($sponsorIds)) {
            $ids = $sponsorIds;
        }

        $ids = array_filter($ids, fn($value) => $value !== '' && $value !== null);
        $ids = array_values(array_unique(array_map(static fn($value) => (int)$value, $ids)));

        $existing = [];
        foreach ($production->getSponsorships() as $sponsorship) {
            $existing[$sponsorship->getSponsor()->getId()] = $sponsorship;
        }

        foreach ($existing as $sponsorId => $sponsorship) {
            if (!in_array($sponsorId, $ids, true)) {
                $production->getSponsorships()->removeElement($sponsorship);
                $this->entityManager->remove($sponsorship);
                unset($existing[$sponsorId]);
            }
        }

        $sponsorRepository = $this->entityManager->getRepository(Sponsor::class);
        foreach ($ids as $sponsorId) {
            if ($sponsorId <= 0 || isset($existing[$sponsorId])) {
                continue;
            }

            $sponsor = $sponsorRepository->find($sponsorId);
            if (!$sponsor) {
                continue;
            }

            $newSponsorship = new Sponsorship($sponsor);
            $newSponsorship->setProduction($production);
            $production->addSponsorship($newSponsorship);
            $this->entityManager->persist($newSponsorship);
        }
    }

    private function handleFeaturedImageUpload(Request $request, Production $production): void
    {
        $uploadedFiles = $request->getUploadedFiles();
        $poster = $uploadedFiles['poster'] ?? null;

        if (!$poster instanceof UploadedFileInterface || $poster->getError() !== UPLOAD_ERR_OK) {
            return;
        }

        if (!$this->isImageUpload($poster)) {
            return;
        }

        try {
            $production->saveFeaturedImageFromUpload($poster);
        } catch (\InvalidArgumentException | \RuntimeException) {
            // Ignore invalid uploads; let other validation flow handle feedback.
        }
    }

    private function deleteFeaturedImageFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = $this->resolveFeaturedImagePath($url);
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    private function resolveFeaturedImagePath(string $url): ?string
    {
        if (!str_starts_with($url, self::UPLOADS_SUBPATH)) {
            return null;
        }

        $relative = ltrim($url, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $this->getPublicRoot() . '/' . $relative;
    }

    private function getPublicRoot(): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        return rtrim($root, '/\\') . '/www';
    }

    private function isImageUpload(UploadedFileInterface $file): bool
    {
        $mediaType = $file->getClientMediaType();
        return is_string($mediaType) && str_starts_with($mediaType, 'image/');
    }
}
