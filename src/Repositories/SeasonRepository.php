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
         $args = array_merge([
            'label'     => null,
            'startDate' => null,
            'endDate'   => null,
            'overview'  => null,
         ], $args);

         $label = (string)($args['label'] ?? '');
         $slug  = (string)preg_replace('/[^A-Za-z0-9-]+/', '-', $label);
         $slug  = strtolower(trim($slug, '-'));

         try {
             $startDate = $args['startDate'] ? new \DateTime($args['startDate']) : null;
             $endDate   = $args['endDate']   ? new \DateTime($args['endDate'])   : null;
         } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid date format. Use YYYY-MM-DD.');
         }

         if ($startDate && $endDate && $endDate <= $startDate) {
             throw new \InvalidArgumentException('End date must be after start date.');
         }

         $season = new Season($slug, $label);

         if ($startDate) {
             $season->setStartDate($startDate);
         }

         if ($endDate) {
             $season->setEndDate($endDate);
         }

         $season->setOverview($args['overview']);

         $this->em->persist($season);
         $this->em->flush();

         return $season;
     }
}
