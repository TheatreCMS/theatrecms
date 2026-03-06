<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Work;
use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Models\RoleType;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class ProductionController
 * @package Clubdeuce\TheatreCMS\Controllers
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

        $this->repository->create($data);

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
        ]);

        /**
         * @var Production $item
         */
        $item = $this->repository->fetch($data['productionId']);
        $season = $this->entityManager->getRepository(Season::class)->find($data['seasonId']);
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

        $this->repository->update($item);

        return $response->withHeader('Location', '/admin/productions');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new NotBlank());
        $metadata->addPropertyConstraint('opening', new Date());
        $metadata->addPropertyConstraint('closing', new Date());
    }
}
