<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Season;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

readonly class SeasonRepository
{
    public function __construct(private EntityManager $em)
    {
    }

    /**
     * @throws ORMException
     */
    public function create(Season $season): Season
    {
        $this->em->persist($season);
        $this->em->flush();
        return $season;
    }

    public function findById(int $id): ?Season
    {
        try {
            return $this->em->find('Clubdeuce\TheatreCMS\Models\Season', $id);
        } catch (OptimisticLockException | ORMException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return null;
        }
    }

    /**
     * @return Season[]
     */
    public function findAll(): array
    {
        $repository = $this->em->getRepository(Season::class);
        return $repository->findAll();
    }

    public function deleteById(int $id): bool
    {
        try {
            if ($item = $this->em->find(Season::class, $id)) {
                $this->em->remove($item);
            }

            $this->em->flush();
            return true;
        } catch (ORMException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function update(Season $season): void
    {
        try {
            $this->em->persist($season);
            $this->em->flush();
        } catch (ORMException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }
}
