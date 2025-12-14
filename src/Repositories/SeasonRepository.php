<?php

namespace Clubdeuce\Theaterpress\Repositories;

use Clubdeuce\Theaterpress\Models\Season;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;

readonly class SeasonRepository
{
    public function __construct(private EntityManager $em) {}

    /**
     * @throws ORMException
     */
    public function create(Season $season): Season
    {
        $this->em->persist($season);
        $this->em->flush();
        return $season;
    }

    public function findById(int $id)
    {
        return $this->em->find('Clubdeuce\Theaterpress\Models\Season', $id);
    }

}