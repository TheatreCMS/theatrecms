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
         $args = array_merge($args, [
            'slug' => null,
            'label' => null,
            'startDate' => null,
            'endDate' => null,
            'overview' => null,
         ]);

         $season = new Season($args['slug'], $args['label']);

         $season->setSlug($args['slug'])
             ->setLabel($args['label'])
             ->setStartDate($args['startDate'])
             ->setEndDate($args['endDate'])
             ->setOverview($args['overview']);

         $this->em->persist($season);
         $this->em->flush();

        return $season;
     }
}
