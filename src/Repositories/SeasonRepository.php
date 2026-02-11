<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Season;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

class SeasonRepository extends BaseRepository
{
     protected string $entityClass = Season::class;

     public function create(array $args): Season
     {
        $season = new Season($args['slug'], $args['label']);

        $season->setStartDate($args['startDate'])
            ->setOverview($args['overview']);

        $this->em->persist($season);
        $this->em->flush();

        return $season;
     }
}
