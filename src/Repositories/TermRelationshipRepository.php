<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use TheatreCMS\Models\Term;
use TheatreCMS\Models\TermRelationship;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * Assigns taxonomy terms to content items, keyed by (content_type, content_id).
 * Deliberately does not extend BaseRepository — like ContentMetaRepository, its rows are
 * not top-level, sluggable, paginated list resources.
 */
class TermRelationshipRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaxonomyRegistry $taxonomies,
    ) {
    }

    /**
     * @return Term[] the item's terms in assignment order, optionally limited to one taxonomy
     */
    public function termsFor(string $contentType, int $contentId, ?string $taxonomy = null): array
    {
        $builder = $this->em->createQueryBuilder()
            ->select('tr', 't')
            ->from(TermRelationship::class, 'tr')
            ->join('tr.term', 't')
            ->where('tr.contentType = :contentType')
            ->andWhere('tr.contentId = :contentId')
            ->setParameter('contentType', $contentType)
            ->setParameter('contentId', $contentId)
            ->orderBy('t.taxonomy', 'ASC')
            ->addOrderBy('tr.position', 'ASC')
            ->addOrderBy('tr.id', 'ASC');

        if ($taxonomy !== null) {
            $builder->andWhere('t.taxonomy = :taxonomy')->setParameter('taxonomy', $taxonomy);
        }

        /** @var TermRelationship[] $relationships */
        $relationships = $builder->getQuery()->getResult();

        return array_map(static fn(TermRelationship $tr): Term => $tr->getTerm(), $relationships);
    }

    /**
     * Replace the item's terms for one taxonomy with the given term ids, in the given order.
     * Terms of other taxonomies on the same item are left untouched.
     *
     * @param int[] $termIds
     * @throws InvalidArgumentException for an unknown taxonomy, a taxonomy that does not apply to
     *         the content type, too many terms for a single-term taxonomy, or ids that are not
     *         terms of this taxonomy
     */
    public function setTerms(string $contentType, int $contentId, string $taxonomy, array $termIds): void
    {
        $definition = $this->taxonomies->require($taxonomy);

        if (!$definition->appliesTo($contentType)) {
            throw new InvalidArgumentException(sprintf(
                'Taxonomy "%s" cannot be assigned to content type "%s".',
                $taxonomy,
                $contentType
            ));
        }

        $termIds = array_values(array_unique(array_map('intval', $termIds)));

        if (!$definition->multiple && count($termIds) > 1) {
            throw new InvalidArgumentException(sprintf('Taxonomy "%s" allows only one term per item.', $taxonomy));
        }

        $terms = $this->fetchTermsInTaxonomy($taxonomy, $termIds);

        $existing = [];
        foreach ($this->relationshipsFor($contentType, $contentId, $taxonomy) as $relationship) {
            $existing[$relationship->getTerm()->getId()] = $relationship;
        }

        foreach ($termIds as $position => $termId) {
            if (isset($existing[$termId])) {
                $existing[$termId]->setPosition($position);
                unset($existing[$termId]);
                continue;
            }

            $this->em->persist(new TermRelationship($terms[$termId], $contentType, $contentId, $position));
        }

        foreach ($existing as $stale) {
            $this->em->remove($stale);
        }

        $this->em->flush();
    }

    /**
     * @return int[] ids of the content items of the given type that carry this term
     */
    public function contentIdsFor(Term $term, string $contentType): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('tr.contentId')
            ->from(TermRelationship::class, 'tr')
            ->where('tr.term = :term')
            ->andWhere('tr.contentType = :contentType')
            ->setParameter('term', $term)
            ->setParameter('contentType', $contentType)
            ->orderBy('tr.contentId', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $rows);
    }

    /**
     * Removes through the entity manager rather than a bulk DQL delete, so relationship
     * entities already loaded in this request don't linger in the unit of work.
     */
    public function deleteAllForContent(string $contentType, int $contentId): void
    {
        $relationships = $this->em->getRepository(TermRelationship::class)->findBy([
            'contentType' => $contentType,
            'contentId' => $contentId,
        ]);

        foreach ($relationships as $relationship) {
            $this->em->remove($relationship);
        }

        $this->em->flush();
    }

    /**
     * @return TermRelationship[]
     */
    private function relationshipsFor(string $contentType, int $contentId, string $taxonomy): array
    {
        return $this->em->createQueryBuilder()
            ->select('tr', 't')
            ->from(TermRelationship::class, 'tr')
            ->join('tr.term', 't')
            ->where('tr.contentType = :contentType')
            ->andWhere('tr.contentId = :contentId')
            ->andWhere('t.taxonomy = :taxonomy')
            ->setParameter('contentType', $contentType)
            ->setParameter('contentId', $contentId)
            ->setParameter('taxonomy', $taxonomy)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $termIds
     * @return array<int, Term> id => term
     */
    private function fetchTermsInTaxonomy(string $taxonomy, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        /** @var Term[] $found */
        $found = $this->em->getRepository(Term::class)->findBy(['id' => $termIds, 'taxonomy' => $taxonomy]);

        $terms = [];
        foreach ($found as $term) {
            $terms[$term->getId()] = $term;
        }

        $missing = array_diff($termIds, array_keys($terms));
        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                'Term id(s) %s are not terms of taxonomy "%s".',
                implode(', ', $missing),
                $taxonomy
            ));
        }

        return $terms;
    }
}
