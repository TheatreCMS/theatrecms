<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Models\Event;
use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Venue;
use Clubdeuce\TheatreCMS\Repositories\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints\Date as DateConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @method EventRepository repository()
 */
class EventController extends BaseController
{
    public function __construct(EventRepository $repository, EntityManagerInterface $em)
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

        try {
            $this->repository->create($data);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }

        return $response->withHeader('Location', '/admin/events');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'eventId' => 0,
            'productionId' => null,
            'venueId' => null,
            'startsAt' => null,
            'status' => 'scheduled',
            'ticketUrl' => null,
            'notes' => null,
            'title' => null,
        ]);

        $item = $this->repository->fetch($data['eventId']);
        if (!$item) {
            return $response->withStatus(404);
        }

        if (!empty($data['productionId'])) {
            $production = $this->entityManager->getRepository(Production::class)->find((int)$data['productionId']);
            if (!$production) {
                return $response->withStatus(400);
            }
            $item->setProduction($production);
        }

        if (isset($data['venueId'])) {
            if (!empty($data['venueId'])) {
                $venue = $this->entityManager->getRepository(Venue::class)->find((int)$data['venueId']);
                if (!$venue) {
                    return $response->withStatus(400);
                }
                $item->setVenue($venue);
            } else {
                // clear venue
                $item->setVenue(null);
            }
        }

        try {
            $startsAt = new \DateTimeImmutable($data['startsAt']);
            $item->setStartsAt($startsAt);
        } catch (\Exception $e) {
            return $response->withStatus(400);
        }

        $item->setStatus($data['status']);
        $item->setTicketUrl($data['ticketUrl']);
        $item->setNotes($data['notes']);
        $item->setTitle($data['title']);

        $this->repository->update($item);

        return $response->withHeader('Location', '/admin/events');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('startsAt', new NotBlank());
        $metadata->addPropertyConstraint('startsAt', new DateConstraint());
    }
}

