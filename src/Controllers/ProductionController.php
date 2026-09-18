<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Models\Media;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Sponsorship;
use TheatreCMS\Models\Work;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\RoleType;
use TheatreCMS\Models\Venue;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Services\ProductionFormOptionsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class ProductionController
 * @package TheatreCMS\Controllers
 *
 * @extends BaseController<ProductionRepository>
 */
class ProductionController extends BaseController
{
    private const CONTENT_TYPE = 'production';

    private const HERO_IMAGE_META_KEY = 'hero_image_id';

    public function __construct(
        ProductionRepository $repository,
        EntityManagerInterface $em,
        Twig $twig,
        private readonly ProductionFormOptionsService $formOptions,
        private readonly ContentMetaRepository $contentMeta
    ) {
        parent::__construct($repository, $twig, $em);
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        [$search] = $this->resolveListQuery($request, []);
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'productions',
            '/admin/productions',
            ['seasons' => $this->formOptions->getSeasons()],
            $search
        );

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/productions/_list.html.twig', $data);
        }

        return $this->twig->render($response, 'admin/productions/index.html.twig', $data);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/productions/create.html.twig', [
            'seasons'  => $this->formOptions->getSeasons(),
            'people'   => $this->formOptions->getPeople(),
            'works'    => $this->formOptions->getWorks(),
            'sponsors' => $this->formOptions->getSponsors(),
            'venues'   => $this->formOptions->getVenues(),
            'heroImageUrl' => null,
            'heroImageId'  => null,
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
            'seasons'    => $this->formOptions->getSeasons(),
            'people'     => $this->formOptions->getPeople(),
            'works'      => $this->formOptions->getWorks(),
            'performers'      => $production->getPerformers()->toArray(),
            'productionTeam'  => $production->getProductionTeam()->toArray(),
            'sponsors'   => $this->formOptions->getSponsors(),
            'venues'     => $this->formOptions->getVenues(),
            'events'     => $this->formOptions->getEventsForProduction((int) $args['id']),
            'activeTab'  => $activeTab,
            'heroImageUrl' => $this->resolveHeroImageUrl($production->getId()),
            'heroImageId'  => $this->contentMeta->get(
                self::CONTENT_TYPE,
                $production->getId(),
                self::HERO_IMAGE_META_KEY
            ),
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

        $production->setFeaturedImage(null);
        $this->repository->update($production);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_featured_image_field.html.twig', [
                'entityType'       => 'production',
                'entityId'         => $production->getId(),
                'featuredImageUrl' => $production->getFeaturedImageUrl(),
                'featuredImageId'  => null,
            ]);
        }

        return $response->withHeader('Location', '/admin/productions/edit/' . $production->getId());
    }

    public function removeHeroImage(Request $request, Response $response, array $args = []): Response
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

        $this->contentMeta->delete(self::CONTENT_TYPE, $production->getId(), self::HERO_IMAGE_META_KEY);

        if ($request->getHeaderLine('HX-Request')) {
            return $this->twig->render($response, 'admin/partials/_featured_image_field.html.twig', [
                'entityType'       => 'production',
                'entityId'         => $production->getId(),
                'field'            => 'hero',
                'label'            => 'Hero Image',
                'deleteUrl'        => '/admin/productions/' . $production->getId() . '/hero-image',
                'featuredImageUrl' => null,
                'featuredImageId'  => null,
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
            'featuredImageId' => null,
            'heroImageId' => null,
        ]);

        $production = $this->repository->create($data);
        $this->applyFeaturedImage($production, $data['featuredImageId']);
        $this->applyHeroImage($production->getId(), $data['heroImageId']);

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

        $editUrl = '/admin/productions/edit/' . $production->getId();

        if ($request->getHeaderLine('HX-Request')) {
            return $response->withHeader('HX-Redirect', $editUrl);
        }

        return $response->withHeader('Location', $editUrl);
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
            'excerpt' => null,
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
            'featuredImageId' => null,
            'heroImageId' => null,
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
            ->setExcerpt($data['excerpt'])
            ->setPromoVideoUrl($data['promoVideoUrl'])
            ->setTicketPurchaseUrl($data['ticketPurchaseUrl']);

        $this->syncWorks($item, $data['works']);

        $this->applyFeaturedImage($item, $data['featuredImageId']);
        $this->applyHeroImage($item->getId(), $data['heroImageId']);

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
                $existingCreatives[$creativeId]->setRole($role)->setPosition($idx);
                unset($existingCreatives[$creativeId]);
                continue;
            }

            $person = $personRepository->find($creativeId);
            if (!$person) {
                continue;
            }

            $item->addToCreativeTeam($person, $role, $idx);
        }

        foreach ($performerIds as $idx => $performerId) {
            if (empty($performerId)) {
                continue;
            }

            $role = $performerRoles[$idx] ?? null;

            if (isset($existingPerformers[$performerId])) {
                $existingPerformers[$performerId]->setRole($role)->setPosition($idx);
                unset($existingPerformers[$performerId]);
                continue;
            }

            $person = $personRepository->find($performerId);
            if (!$person) {
                continue;
            }

            $item->addPerformer($person, $role, $idx);
        }

        foreach ($productionTeamIds as $idx => $memberId) {
            if (empty($memberId)) {
                continue;
            }

            $role = $productionTeamRoles[$idx] ?? null;

            if (isset($existingProductionTeam[$memberId])) {
                $existingProductionTeam[$memberId]->setRole($role)->setPosition($idx);
                unset($existingProductionTeam[$memberId]);
                continue;
            }

            $person = $personRepository->find($memberId);
            if (!$person) {
                continue;
            }

            $item->addToProductionTeam($person, $role, $idx);
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

    /**
     * Reconciles a production's works against the submitted (ordered) list of work IDs,
     * preserving the display order the user arranged in the works picker and removing
     * any join rows for works that are no longer selected.
     */
    private function syncWorks(Production $production, array|string $workIds): void
    {
        if (!is_array($workIds)) {
            $workIds = !empty($workIds) ? explode(',', (string) $workIds) : [];
        }

        $workIds = array_filter(array_map('trim', array_map('strval', $workIds)), static function (string $workId): bool {
            return $workId !== '';
        });
        $workIds = array_values(array_unique(array_map('intval', $workIds)));

        $existing = [];
        foreach ($production->getProductionWorks() as $productionWork) {
            $existing[$productionWork->getWork()->getId()] = $productionWork;
        }

        foreach ($existing as $workId => $productionWork) {
            if (!in_array($workId, $workIds, true)) {
                $production->getProductionWorks()->removeElement($productionWork);
                $this->entityManager->remove($productionWork);
                unset($existing[$workId]);
            }
        }

        $worksRepository = $this->entityManager->getRepository(Work::class);

        foreach ($workIds as $position => $workId) {
            if (isset($existing[$workId])) {
                $existing[$workId]->setPosition($position);
                continue;
            }

            $work = $worksRepository->find($workId);
            if (!$work instanceof Work) {
                continue;
            }

            $production->addWork($work, $position);
        }
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

    private function applyFeaturedImage(Production $production, mixed $featuredImageId): void
    {
        if (empty($featuredImageId)) {
            $production->setFeaturedImage(null);
            return;
        }

        $image = $this->entityManager->getRepository(Media::class)->find((int) $featuredImageId);

        if ($image instanceof Media && !$image->isImage()) {
            throw new \InvalidArgumentException('Featured media must be an image.');
        }

        $production->setFeaturedImage($image);
    }

    private function applyHeroImage(int $productionId, mixed $heroImageId): void
    {
        if (empty($heroImageId)) {
            $this->contentMeta->delete(self::CONTENT_TYPE, $productionId, self::HERO_IMAGE_META_KEY);
            return;
        }

        $image = $this->entityManager->getRepository(Media::class)->find((int) $heroImageId);

        if (!$image instanceof Media) {
            $this->contentMeta->delete(self::CONTENT_TYPE, $productionId, self::HERO_IMAGE_META_KEY);
            return;
        }

        if (!$image->isImage()) {
            throw new \InvalidArgumentException('Hero media must be an image.');
        }

        $this->contentMeta->set(self::CONTENT_TYPE, $productionId, self::HERO_IMAGE_META_KEY, (string) $image->getId());
    }

    private function resolveHeroImageUrl(int $productionId): ?string
    {
        $heroImageId = $this->contentMeta->get(self::CONTENT_TYPE, $productionId, self::HERO_IMAGE_META_KEY);

        if (empty($heroImageId)) {
            return null;
        }

        $image = $this->entityManager->getRepository(Media::class)->find((int) $heroImageId);

        return $image instanceof Media ? $image->getUrl() : null;
    }
}
