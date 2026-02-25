<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Event;
use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Venue;
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

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
