<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Person;
use Doctrine\ORM\EntityManagerInterface;

final class PersonRepository extends BaseRepository
{
    protected string $entityClass = Person::class;

    public function create(array $args): Person
    {
        $args = array_merge([
            'firstName' => null,
            'lastName' => null,
            'biography' => null,
            'headshotUrl' => null,
        ], $args);

        $person = new Person();

        $person->setFirstName($args['firstName'])
            ->setLastName($args['lastName'])
            ->setBiography($args['biography'])
            ->setHeadshotUrl($args['headshotUrl']);

        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }
}
