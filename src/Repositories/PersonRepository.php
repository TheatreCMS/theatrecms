<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Person;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;

final readonly class PersonRepository
{
    public function __construct(private EntityManager $em)
    {
    }

    /**
     * @throws ORMException
     */
    public function create(string $name, string $biography = '', string $headshotUrl = ''): Person
    {
        $person = new Person($name, $biography, $headshotUrl);
        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }
}
