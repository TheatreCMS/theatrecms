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
use TheatreCMS\Repositories\ProductionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
    public function __construct(ProductionRepository $repository, EntityManagerInterface $em)
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

        $data = $this->parseArgs($data, [
            'sponsorshipSponsorIds' => [],
            'venueId' => null,
        ]);

        $production = $this->repository->create($data);

        $this->syncSponsorships($production, $data['sponsorshipSponsorIds']);
        $this->entityManager->flush();

        return $response->withHeader('Location', '/admin/productions');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
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
            'sponsorshipSponsorIds' => [],
            'venueId' => 0,
        ]);

        /**
         * @var Production $item
         */
        $item = $this->repository->fetch($data['productionId']);
        $season = $this->entityManager->getRepository(Season::class)->find($data['seasonId']);
        $venue = $this->entityManager->getRepository(Venue::class)->find($data['venueId']);
        if (!$venue) {
            return $response->withStatus(400);
        }
        $worksRepository = $this->entityManager->getRepository(Work::class);

        $works = is_array($data['works']) ? $data['works'] : explode(',', $data['works']);

        $works = array_map(function ($workId) use ($worksRepository) {
            return $worksRepository->find($workId);
        }, $works);

        try {
            $opening = new \DateTime($data['opening']);
            $closing = new \DateTime($data['closing']);
        } catch (\Exception $e) {
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

        $creativeIds = is_array($data['creativeIds']) ? $data['creativeIds'] : [];
        $creativeRoles = is_array($data['creativeRoles']) ? $data['creativeRoles'] : [];
        $performerIds = is_array($data['performerIds']) ? $data['performerIds'] : [];
        $performerRoles = is_array($data['performerRoles']) ? $data['performerRoles'] : [];

        $existingCreatives = [];
        $existingPerformers = [];

        foreach ($item->getPeople() as $productionPerson) {
            $personId = $productionPerson->getPerson()->getId();
            $roleType = $productionPerson->getRoleType();

            if ($roleType === RoleType::Creative) {
                $existingCreatives[$personId] = $productionPerson;
            } elseif ($roleType === RoleType::Cast) {
                $existingPerformers[$personId] = $productionPerson;
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

        foreach ($existingCreatives as $staleCreative) {
            $item->getPeople()->removeElement($staleCreative);
            $this->entityManager->remove($staleCreative);
        }

        foreach ($existingPerformers as $stalePerformer) {
            $item->getPeople()->removeElement($stalePerformer);
            $this->entityManager->remove($stalePerformer);
        }

        $this->syncSponsorships($item, $data['sponsorshipSponsorIds']);

        $this->repository->update($item);

        return $response->withHeader('Location', '/admin/productions');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
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
}
