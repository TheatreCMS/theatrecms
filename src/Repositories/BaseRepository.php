<?php

namespace TheatreCMS\Repositories;

use TheatreCMS\Models\Person;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\User;
use TheatreCMS\Models\Work;
use Doctrine\ORM\EntityManagerInterface;

abstract class BaseRepository
{
    protected string $entityClass;

    public function __construct(protected EntityManagerInterface $em)
    {
    }

    abstract public function create(array $args);

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

    /**
     * @return array<Person|Season|User|Work>
     */
    public function fetchAll(): array
    {
        return $this->em->getRepository($this->entityClass)->findAll();
    }

    public function fetch(int $id)
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['id' => $id]);
    }

    public function fetchBySlug(string $slug)
    {
        return $this->em->getRepository($this->entityClass)->findOneBy(['slug' => $slug]);
    }

    public function delete($item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }

    public function update($item): void
    {
        $this->em->persist($item);
        $this->em->flush();
    }

    protected function defaultQueryArgs(): array
    {
        return [
            'orderBy' => ['id' => 'ASC'],
            'limit'   => 10,
            'offset'  => 0,
        ];
    }

    protected function generateUniqueSlug(string $string): string
    {
        // first prepare the string by lowercasing and replacing non-alphanumeric characters with hyphens
        $processed = strtolower($string);
        $processed = preg_replace('/[^a-z0-9]+/i', '-', $processed);
        $slug = $processed = trim($processed, '-');
        $i = 0;

        // if the slug already exists, append a number and increment until we find a unique slug
        while($this->slugExists($slug)){
            $i++;
            $slug = $processed . '-' . $i;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $existing = $this->em->getRepository($this->entityClass)->findOneBy(['slug' => $slug]);
        return $existing !== null;
    }
}
