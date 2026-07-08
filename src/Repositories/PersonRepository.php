<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Person;
use Doctrine\ORM\QueryBuilder;

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

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.lastName', $alias), 'ASC')
            ->addOrderBy(sprintf('%s.firstName', $alias), 'ASC')
            ->addOrderBy(sprintf('%s.id', $alias), 'ASC');
    }
}
