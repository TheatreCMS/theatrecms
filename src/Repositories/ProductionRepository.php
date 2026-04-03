<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Venue;
use TheatreCMS\Models\Work;
use TheatreCMS\Models\Person;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class ProductionRepository extends BaseRepository
{

    protected string $entityClass = Production::class;

    public function fetchAll(): array
    {
        return $this->em->createQueryBuilder()
            ->select('p')
            ->from(Production::class, 'p')
            ->orderBy('p.opening', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function create(array $args): Production
    {
        $args = array_merge([
            'name' => null,
            'seasonId' => null,
            'opening' => null,
            'closing' => null,
            'description' => null,
            'excerpt' => null,
            'runtime' => null,
            'ageRecommendation' => null,
            'contentAdvisory' => null,
            'promoVideoUrl' => null,
            'ticketPurchaseUrl' => null,
            'works' => null, // comma separated ids or titles
            'people' => null, // lines of "id|role" or "Name|role"
        ], $args);

        $name = trim((string)$args['name']);
        if (empty($name)) {
            throw new \InvalidArgumentException('Production name is required.');
        }

        $seasonId = (int)($args['seasonId'] ?? 0);
        if (!$seasonId) {
            throw new \InvalidArgumentException('Season ID is required.');
        }

        $season = $this->em->getRepository(Season::class)->find($seasonId);
        if (!$season) {
            throw new \InvalidArgumentException('Season not found.');
        }

        $venueId = (int)($args['venueId'] ?? 0);
        if (!$venueId) {
            throw new \InvalidArgumentException('Venue ID is required.');
        }

        $venue = $this->em->getRepository(Venue::class)->find($venueId);
        if (!$venue) {
            throw new \InvalidArgumentException('Venue not found.');
        }

        $production = new Production($name, $season);
        $production->setVenue($venue);

        $production->setSlug($this->generateUniqueSlug($name));

        if (!empty($args['description'])) {
            $production->setDescription($args['description']);
        }

        if (!empty($args['excerpt'])) {
            $production->setExcerpt($args['excerpt']);
        }

        if (!empty($args['runtime'])) {
            $production->setRuntime((int)$args['runtime']);
        }

        if (!empty($args['ageRecommendation'])) {
            $production->setAgeRecommendation($args['ageRecommendation']);
        }

        if (!empty($args['contentAdvisory'])) {
            $production->setContentAdvisory($args['contentAdvisory']);
        }

        if (!empty($args['promoVideoUrl'])) {
            $production->setPromoVideoUrl($args['promoVideoUrl']);
        }

        if (!empty($args['ticketPurchaseUrl'])) {
            $production->setTicketPurchaseUrl($args['ticketPurchaseUrl']);
        }

        if (!empty($args['opening'])) {
            try {
                $opening = new DateTime($args['opening']);
                $production->setOpening($opening);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid opening date format.');
            }
        }

        if (!empty($args['closing'])) {
            try {
                $closing = new DateTime($args['closing']);
                $production->setClosing($closing);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid closing date format.');
            }
        }

        // Persist production first so relations can reference it
        $this->em->persist($production);

        // @todo update people and works if provided

        $this->em->flush();

        return $production;
    }

    public function getBySlug(string $slug): object
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['slug' => $slug]);
    }
}
