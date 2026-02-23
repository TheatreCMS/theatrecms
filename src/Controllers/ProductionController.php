<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Work;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\TextUI\XmlConfiguration\Validator;
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