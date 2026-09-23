<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use TheatreCMS\Models\Term;
use TheatreCMS\Models\TermRelationship;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

class TermRepository extends BaseRepository
{
    protected string $entityClass = Term::class;

    public function __construct(EntityManagerInterface $em, private readonly TaxonomyRegistry $taxonomies)
    {
        parent::__construct($em);
    }

    /**
     * @param array{taxonomy?: string, name?: string, slug?: string|null, description?: string|null} $args
     */
    public function create(array $args): Term
    {
        $taxonomy = (string) ($args['taxonomy'] ?? '');
        $name = trim((string) ($args['name'] ?? ''));

        $this->taxonomies->require($taxonomy);

        if ($name === '') {
            throw new InvalidArgumentException('A term name is required.');
        }

        $slugSource = trim((string) ($args['slug'] ?? '')) ?: $name;
        $term = new Term($taxonomy, $name, $this->generateUniqueTermSlug($taxonomy, $slugSource));
        $term->setDescription($args['description'] ?? null);

        $this->em->persist($term);
        $this->em->flush();

        return $term;
    }

    /**
     * Removes the term's assignments through the entity manager as well: the FK's ON DELETE
     * CASCADE cleans the rows up, but any relationship entities already loaded in this request
     * would otherwise stay managed while pointing at a removed term, breaking the next flush.
     *
     * @param Term $item
     */
    public function delete($item): void
    {
        $relationships = $this->em->getRepository(TermRelationship::class)->findBy(['term' => $item]);

        foreach ($relationships as $relationship) {
            $this->em->remove($relationship);
        }

        parent::delete($item);
    }

    /**
     * @return Term[] every term in the taxonomy, alphabetically
     */
    public function fetchByTaxonomy(string $taxonomy): array
    {
        return $this->em->getRepository(Term::class)->findBy(['taxonomy' => $taxonomy], ['name' => 'ASC']);
    }

    public function fetchBySlugInTaxonomy(string $taxonomy, string $slug): ?Term
    {
        return $this->em->getRepository(Term::class)->findOneBy(['taxonomy' => $taxonomy, 'slug' => $slug]);
    }

    /**
     * Slugify the source string and suffix it (-1, -2, ...) until it is unique within the taxonomy.
     * Term slugs are scoped per taxonomy, unlike the table-wide uniqueness of generateUniqueSlug().
     */
    public function generateUniqueTermSlug(string $taxonomy, string $source, ?int $ignoreTermId = null): string
    {
        $base = $this->slugify($source) ?: 'term';
        $slug = $base;
        $i = 0;

        while (true) {
            $existing = $this->fetchBySlugInTaxonomy($taxonomy, $slug);
            if ($existing === null || $existing->getId() === $ignoreTermId) {
                return $slug;
            }

            $i++;
            $slug = $base . '-' . $i;
        }
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.name', $alias), 'ASC')
            ->addOrderBy(sprintf('%s.id', $alias), 'ASC');
    }

    protected function applySearchFilter(QueryBuilder $builder, string $alias, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $builder->andWhere(sprintf('%1$s.name LIKE :search OR %1$s.slug LIKE :search', $alias))
            ->setParameter('search', '%' . $search . '%');
    }

    protected function applyRequestedSort(QueryBuilder $builder, string $alias, string $sort, string $direction): bool
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        if ($sort === 'name' || $sort === 'slug') {
            $builder->orderBy(sprintf('%s.%s', $alias, $sort), $direction)
                ->addOrderBy(sprintf('%s.id', $alias), 'ASC');
            return true;
        }

        return false;
    }

    /**
     * Supports a `taxonomy` criterion so a per-taxonomy admin list can use fetchPage().
     */
    protected function applyCriteria(QueryBuilder $builder, string $alias, array $criteria): void
    {
        if (isset($criteria['taxonomy']) && $criteria['taxonomy'] !== '') {
            $builder->andWhere(sprintf('%s.taxonomy = :taxonomy', $alias))
                ->setParameter('taxonomy', (string) $criteria['taxonomy']);
        }
    }
}
