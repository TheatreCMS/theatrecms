<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use TheatreCMS\Models\TermRelationship;

abstract class BaseRepository implements PaginatedRepositoryInterface
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
     * @return array<int, object>
     */
    public function fetchAll(): array
    {
        return $this->createListQueryBuilder()->getQuery()->getResult();
    }

    /**
     * @return array{items: array<int, object>, total: int, page: int, perPage: int}
     */
    public function fetchPage(
        int $page = 1,
        int $perPage = 25,
        string $search = '',
        string $sort = '',
        string $direction = 'asc',
        array $criteria = []
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return [
            'items' => $this->createListQueryBuilder('e', $search, $sort, $direction, $criteria)
                ->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage)
                ->getQuery()
                ->getResult(),
            'total' => $this->countAll('e', $search, $criteria),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * The publicly visible items among the given ids, in the repository's normal list order.
     * Used by frontend listings (e.g. term archives) that know ids but not what's visible.
     *
     * @param int[] $ids
     * @return array<int, object>
     */
    public function fetchPublicByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $builder = $this->createListQueryBuilder()
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', array_values($ids));

        $this->applyPublicFilter($builder, 'e');

        return $builder->getQuery()->getResult();
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
        $contentType = $this->taxonomyContentType();
        if ($contentType !== null) {
            // content_id is a soft reference, so the DB can't cascade this for us
            $relationships = $this->em->getRepository(TermRelationship::class)->findBy([
                'contentType' => $contentType,
                'contentId' => $item->getId(),
            ]);

            foreach ($relationships as $relationship) {
                $this->em->remove($relationship);
            }
        }

        $this->em->remove($item);
        $this->em->flush();
    }

    public function update($item): void
    {
        $this->em->persist($item);
        $this->em->flush();
    }

    /**
     * The singular content-type key (e.g. 'work') this repository's entities are stored under
     * in `term_relationships`, so delete() can clean up their term assignments.
     * Null (the default) for entities that can't carry taxonomy terms.
     */
    protected function taxonomyContentType(): ?string
    {
        return null;
    }

    protected function defaultQueryArgs(): array
    {
        return [
            'orderBy' => ['id' => 'ASC'],
            'limit'   => 10,
            'offset'  => 0,
        ];
    }

    protected function createListQueryBuilder(
        string $alias = 'e',
        string $search = '',
        string $sort = '',
        string $direction = 'asc',
        array $criteria = []
    ): QueryBuilder {
        $builder = $this->em->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        $this->applySearchFilter($builder, $alias, $search);
        $this->applyCriteria($builder, $alias, $criteria);

        if ($sort === '' || !$this->applyRequestedSort($builder, $alias, $sort, $direction)) {
            $this->applyListOrder($builder, $alias);
        }

        return $builder;
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.id', $alias), 'ASC');
    }

    /**
     * Restrict the list/count query builders to items matching the given search term.
     * No-op by default; override in repositories that support searching.
     */
    protected function applySearchFilter(QueryBuilder $builder, string $alias, string $search): void
    {
    }

    /**
     * Apply a user-requested sort column/direction to the list query builder.
     * Returns true if the sort key was recognized and applied, false to fall back to applyListOrder().
     * No-op by default; override in repositories that support user-driven sorting.
     */
    protected function applyRequestedSort(QueryBuilder $builder, string $alias, string $sort, string $direction): bool
    {
        return false;
    }

    /**
     * Restrict the list/count query builders to items matching arbitrary,
     * repository-specific filter criteria (e.g. a `type` facet).
     * No-op by default; override in repositories that support such filtering.
     *
     * @param array<string, mixed> $criteria
     */
    protected function applyCriteria(QueryBuilder $builder, string $alias, array $criteria): void
    {
    }

    /**
     * Restrict a frontend query builder to items the public may see (e.g. published posts).
     * No-op by default; override in repositories whose entities have a draft/private state.
     */
    protected function applyPublicFilter(QueryBuilder $builder, string $alias): void
    {
    }

    protected function countAll(string $alias = 'e', string $search = '', array $criteria = []): int
    {
        $builder = $this->em->createQueryBuilder()
            ->select(sprintf('COUNT(%s.id)', $alias))
            ->from($this->entityClass, $alias);

        $this->applySearchFilter($builder, $alias, $search);
        $this->applyCriteria($builder, $alias, $criteria);

        return (int) $builder->getQuery()->getSingleScalarResult();
    }

    protected function generateUniqueSlug(string $string): string
    {
        $slug = $processed = $this->slugify($string);
        $i = 0;

        // if the slug already exists, append a number and increment until we find a unique slug
        while ($this->slugExists($slug)) {
            $i++;
            $slug = $processed . '-' . $i;
        }

        return $slug;
    }

    /**
     * Lowercase the string and collapse runs of non-alphanumeric characters into single hyphens.
     */
    protected function slugify(string $string): string
    {
        return trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($string)), '-');
    }

    protected function slugExists(string $slug): bool
    {
        $existing = $this->em->getRepository($this->entityClass)->findOneBy(['slug' => $slug]);
        return $existing !== null;
    }
}
