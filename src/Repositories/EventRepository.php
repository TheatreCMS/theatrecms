<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Venue;
use Doctrine\ORM\EntityManagerInterface;

class EventRepository extends BaseRepository
{
    protected string $entityClass = Event::class;

    public function create(array $args): Event
    {
        $args = array_merge([
            'productionId' => null,
            'venueId' => null,
            'startsAt' => null,
            'status' => 'scheduled',
            'ticketUrl' => null,
            'notes' => null,
            'title' => null,
        ], $args);

        if (empty($args['startsAt'])) {
            throw new \InvalidArgumentException('Start date/time is required.');
        }

        try {
            $startsAt = new \DateTimeImmutable($args['startsAt']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid startsAt date format.');
        }

        $production = null;
        if (!empty($args['productionId'])) {
            $production = $this->em->getRepository(Production::class)->find((int)$args['productionId']);
            if (!$production) {
                throw new \InvalidArgumentException('Production not found.');
            }
        }

        $venue = null;
        if (!empty($args['venueId'])) {
            $venue = $this->em->getRepository(Venue::class)->find((int)$args['venueId']);
            if (!$venue) {
                throw new \InvalidArgumentException('Venue not found.');
            }
        }

        $event = new Event($startsAt, $args['status'], $production, $args['title'] ?? null);

        if ($venue) {
            $event->setVenue($venue);
        }

        if (!empty($args['ticketUrl'])) {
            $event->setTicketUrl($args['ticketUrl']);
        }

        if (!empty($args['notes'])) {
            $event->setNotes($args['notes']);
        }

        if (!empty($args['title'])) {
            $event->setTitle($args['title']);
        }

        $event->setSlug($this->generateEventSlug($event));

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }

    public function update($event): void
    {
        if (empty($event->getSlug())) {
            $event->setSlug($this->generateEventSlug($event));
        }

        parent::update($event);
    }

    /**
     * @return Event[]
     */
    public function fetchByProduction(int $productionId): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.production = :productionId')
            ->setParameter('productionId', $productionId)
            ->orderBy('e.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function generateEventSlug(Event $event): string
    {
        $date = $event->getStartsAt()->format('Y-m-d');

        if (!empty($event->getTitle())) {
            $base = $date . '-' . $event->getTitle();
        } elseif ($event->getProduction() !== null) {
            $base = $date . '-' . $event->getProduction()->getName();
        } else {
            $base = $event->getStartsAt()->format('Y-m-d-His');
        }

        return $this->generateUniqueSlug($base);
    }
}
