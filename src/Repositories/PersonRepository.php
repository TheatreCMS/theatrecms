<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Person;
use TheatreCMS\Traits\EagerLoadsHeroImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @method Person[] query(array $args = [])
 * @method Person[] fetchAll()
 * @method Person|null fetch(int $id)
 */
final class PersonRepository extends BaseRepository
{
    use EagerLoadsHeroImage;

    protected string $entityClass = Person::class;

    public function __construct(EntityManagerInterface $em, private readonly ContentMetaRepository $contentMeta)
    {
        parent::__construct($em);
    }

    protected function heroImageContentType(): string
    {
        return 'person';
    }

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

    protected function applySearchFilter(QueryBuilder $builder, string $alias, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $builder->andWhere(sprintf('%s.firstName LIKE :search OR %s.lastName LIKE :search', $alias, $alias))
            ->setParameter('search', '%' . $search . '%');
    }
}
