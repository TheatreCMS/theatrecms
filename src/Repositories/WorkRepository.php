<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Work;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class WorkRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function fetchAll(): array
    {
        return $this->em->getRepository(Work::class)->findAll();
    }

    public function fetch(int $id): ?Work
    {
        return $this->em->getRepository(Work::class)->find($id);
    }

    public function create(array $args): bool|Work
    {
        $work = new Work();

        $work->setTitle($args['title'])
            ->setDescription($args['synopsis'] ?? '');

        try {
            $this->em->persist($work);
            $this->em->flush();
            return $work;
        } catch (ORMException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }
}
