<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Work;
use Clubdeuce\TheatreCMS\Models\Person;
use Doctrine\ORM\EntityManagerInterface;

class ProductionRepository extends BaseRepository
{

    protected string $entityClass = Production::class;

    public function __construct(protected EntityManagerInterface $em)
    {
    }

    public function create(array $args): Production
    {
        $args = array_merge([
            'name' => null,
            'seasonId' => null,
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

        $production = new Production($name, $season);

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

        // Persist production first so relations can reference it
        $this->em->persist($production);

        // Handle works: comma separated list of ids or titles
        if (!empty($args['works'])) {
            $works = array_map('trim', explode(',', (string)$args['works']));
            foreach ($works as $w) {
                if (is_numeric($w)) {
                    $work = $this->em->getRepository(Work::class)->find((int)$w);
                    if ($work) {
                        $production->addWork($work);
                    }
                } elseif (!empty($w)) {
                    // Create new work with title
                    $work = new Work();
                    $work->setTitle($w);
                    $this->em->persist($work);
                    $production->addWork($work);
                }
            }
        }

        // Handle people: each line "id|role" or "Name|role". We'll default to cast role type.
        if (!empty($args['people'])) {
            $lines = preg_split('/\r?\n/', (string)$args['people']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                $parts = array_map('trim', explode('|', $line));
                $identifier = $parts[0] ?? '';
                $role = $parts[1] ?? null;

                if (is_numeric($identifier)) {
                    $person = $this->em->getRepository(Person::class)->find((int)$identifier);
                    if ($person) {
                        $production->addPerformer($person, $role);
                    }
                } else {
                    // Try to create a person from a name (split into first/last)
                    $names = preg_split('/\s+/', $identifier);
                    $first = $names[0] ?? '';
                    $last = count($names) > 1 ? implode(' ', array_slice($names, 1)) : '';

                    $person = new Person();
                    $person->setFirstName($first)
                        ->setLastName($last)
                        ->setBiography('')
                        ->setHeadshotUrl('');

                    $this->em->persist($person);
                    $production->addPerformer($person, $role);
                }
            }
        }

        $this->em->flush();

        return $production;
    }
}