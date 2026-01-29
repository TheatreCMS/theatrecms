<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Person;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

final readonly class PersonRepository
{
    public function __construct(private EntityManager $em)
    {
    }

    public function create(string $name, string $biography = '', string $headshotUrl = ''): Person
    {
        $person = new Person($name, $biography, $headshotUrl);
        return $this->save($person);
    }

    public function save(Person $person): bool|Person
    {
        try {
            $this->em->persist($person);
            $this->em->flush();
            return $person;
        } catch (ORMException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * @return array<Person>
     */
    public function fetchAll(): array
    {
        return $this->em->getRepository(Person::class)->findAll();
    }

    public function fetch(int $id): ?Person
    {
        return $this->em->getRepository(Person::class)->find($id);
    }
}
