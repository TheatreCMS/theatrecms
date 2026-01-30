<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Work;
use Doctrine\ORM\EntityManagerInterface;

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
}