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
        $args = array_merge($this->defaultQueryArgs(), $args);

        $builder = $this->em->createQueryBuilder()
            ->select('e')
            ->from($this->entityClass, 'e')
            ->setMaxResults($args['limit'])
            ->setFirstResult($args['offset']);

        return $builder->getQuery()->getArrayResult();
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

    protected function defaultQueryArgs(): array
    {
        return [
            'orderBy' => ['id' => 'ASC'],
            'limit'   => 10,
            'offset'  => 0,
        ];
    }
}
