<?php

namespace Clubdeuce\TheatreCMS\Repositories;

use Clubdeuce\TheatreCMS\Models\Person;

/**
 * @method Person[] query(array $args = [])
 * @method Person[] fetchAll()
 * @method Person|null fetch(int $id)
 */
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
            ->setHeadshotUrl($args['headshotUrl'])
            ->setSlug($this->generateUniqueSlug($person->getFirstName() . ' ' . $person->getLastName()));


        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }

    public function fetchAll(): array
    {
        return $this->em->getRepository(Person::class)->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']);
    }
}
