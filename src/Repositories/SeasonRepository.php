<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Season;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\QueryBuilder;

class SeasonRepository extends BaseRepository
{
    protected string $entityClass = Season::class;

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.startDate', $alias), 'DESC')
            ->addOrderBy(sprintf('%s.id', $alias), 'DESC');
    }

    public function create(array $args): Season
    {
        $args = array_merge([
           'label'            => null,
           'startDate'        => null,
           'endDate'          => null,
           'overview'         => null,
           'featuredImageUrl' => null,
        ], $args);

        try {
            $startDate = $args['startDate'] ? new \DateTime($args['startDate']) : null;
            $endDate   = $args['endDate']   ? new \DateTime($args['endDate'])   : null;
        } catch (\Exception $e) {
               throw new \InvalidArgumentException('Invalid date format. Use YYYY-MM-DD.');
        }

        if ($startDate && $endDate && $endDate <= $startDate) {
            throw new \InvalidArgumentException('End date must be after start date.');
        }


        $season = new Season($this->generateUniqueSlug($args['label']), $args['label']);

        if ($startDate) {
            $season->setStartDate($startDate);
        }

        if ($endDate) {
            $season->setEndDate($endDate);
        }

        $season->setOverview($args['overview']);
        $season->setFeaturedImageUrl($args['featuredImageUrl']);

        $this->em->persist($season);
        $this->em->flush();

        return $season;
    }
}
