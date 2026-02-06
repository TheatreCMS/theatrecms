<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Work;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

class WorkRepository extends Base
{
    protected string $entityClass = Work::class;

    public function create(array $args): bool|Work
    {
        $work = new Work();

        $work->setTitle($args['title'])
            ->setDescription($args['description'] ?? '');

        $this->em->persist($work);
        $this->em->flush();
        return $work;
    }
}
