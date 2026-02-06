<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Work;
use Doctrine\ORM\EntityManagerInterface;

abstract class Base
{
    protected string $entityClass;

    public function __construct(protected EntityManagerInterface $em)
    {
    }

    public function query(array $args = []): array
    {
        return $this->em->getRepository($this->entityClass)->findBy($args, ['title' => 'ASC'], 10, null);
    }

    public function fetchAll(): array
    {
        return $this->em->getRepository($this->entityClass)->findAll();
    }

    public function fetch(int $id): ?Work
    {
        return $this->em->getRepository(Work::class)->find($id);
    }

    abstract public function create(array $args);
}
