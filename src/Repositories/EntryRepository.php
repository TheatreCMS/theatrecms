<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use LogicException;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Entry;

/**
 * Entries of registered content types (see `register_content_type()`), which all share the
 * `entries` table. Every query is scoped to one content type: bind the type with
 * `forType()`, which returns a copy of the repository, e.g.
 *
 *     $entries->forType('press_release')->fetchBySlug('season-announced');
 *
 * An unbound repository throws instead of reading entries of every type, and slugs are
 * unique within a type, so two types can share one.
 */
class EntryRepository extends BaseRepository
{
    protected string $entityClass = Entry::class;

    private ?string $contentType = null;

    /**
     * A copy of this repository whose queries only see entries of `$contentType`.
     */
    public function forType(string $contentType): static
    {
        $repository = clone $this;
        $repository->contentType = $contentType;

        return $repository;
    }

    /**
     * @param array{
     *     title?: string,
     *     slug?: string|null,
     *     status?: ContentStatus|string,
     *     content?: string|null,
     *     excerpt?: string|null
     * } $args
     * @throws InvalidArgumentException for a missing title or an invalid status
     */
    public function create(array $args): Entry
    {
        $contentType = $this->requireType();

        if (empty($args['title'])) {
            throw new InvalidArgumentException('Title is required.');
        }

        $status = $args['status'] ?? ContentStatus::DRAFT;
        $status = $status instanceof ContentStatus ? $status : ContentStatus::tryFrom((string) $status);

        if ($status === null) {
            throw new InvalidArgumentException('Invalid status.');
        }

        $entry = new Entry([
            'contentType' => $contentType,
            'title' => $args['title'],
            'slug' => $this->generateUniqueSlug($args['slug'] ?? $args['title']),
            'status' => $status,
        ]);
        $entry->setContent($args['content'] ?? null);
        $entry->setExcerpt($args['excerpt'] ?? null);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    public function query(array $args = []): array
    {
        $args = array_merge($this->defaultQueryArgs(), $args);

        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(Entry::class, 'e')
            ->where('e.contentType = :contentType')
            ->setParameter('contentType', $this->requireType())
            ->setMaxResults($args['limit'])
            ->setFirstResult($args['offset'])
            ->getQuery()
            ->getArrayResult();
    }

    public function fetch(int $id): ?Entry
    {
        return $this->em->getRepository(Entry::class)->findOneBy([
            'id' => $id,
            'contentType' => $this->requireType(),
        ]);
    }

    public function fetchBySlug(string $slug): ?Entry
    {
        return $this->em->getRepository(Entry::class)->findOneBy([
            'slug' => $slug,
            'contentType' => $this->requireType(),
        ]);
    }

    /**
     * @throws InvalidArgumentException for an entry of another content type
     */
    public function update($item): void
    {
        $this->assertOwnType($item);
        parent::update($item);
    }

    /**
     * @throws InvalidArgumentException for an entry of another content type
     */
    public function delete($item): void
    {
        $this->assertOwnType($item);
        parent::delete($item);
    }

    protected function taxonomyContentType(): ?string
    {
        return $this->requireType();
    }

    protected function applyCriteria(QueryBuilder $builder, string $alias, array $criteria): void
    {
        $builder->andWhere(sprintf('%s.contentType = :contentType', $alias))
            ->setParameter('contentType', $this->requireType());
    }

    protected function applyListOrder(QueryBuilder $builder, string $alias): void
    {
        $builder->orderBy(sprintf('%s.publishedAt', $alias), 'DESC')
            ->addOrderBy(sprintf('%s.id', $alias), 'DESC');
    }

    protected function applySearchFilter(QueryBuilder $builder, string $alias, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $builder->andWhere($builder->expr()->orX(
            sprintf('%s.title LIKE :search', $alias),
            sprintf('%s.excerpt LIKE :search', $alias),
        ))->setParameter('search', '%' . $search . '%');
    }

    protected function applyRequestedSort(QueryBuilder $builder, string $alias, string $sort, string $direction): bool
    {
        if (!in_array($sort, ['title', 'status', 'publishedAt', 'modifiedAt'], true)) {
            return false;
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $builder->orderBy(sprintf('%s.%s', $alias, $sort), $direction)
            ->addOrderBy(sprintf('%s.id', $alias), 'ASC');

        return true;
    }

    protected function applyPublicFilter(QueryBuilder $builder, string $alias): void
    {
        $builder->andWhere(sprintf('%s.status = :publicStatus', $alias))
            ->setParameter('publicStatus', ContentStatus::PUBLISHED);
    }

    protected function slugExists(string $slug): bool
    {
        return $this->fetchBySlug($slug) !== null;
    }

    /**
     * @throws LogicException when no content type is bound (see forType())
     */
    private function requireType(): string
    {
        if ($this->contentType === null) {
            throw new LogicException('EntryRepository has no content type; call forType() first.');
        }

        return $this->contentType;
    }

    private function assertOwnType(Entry $entry): void
    {
        if ($entry->getContentType() !== $this->requireType()) {
            throw new InvalidArgumentException(sprintf(
                'Entry %d is a "%s", not a "%s".',
                $entry->getId(),
                $entry->getContentType(),
                $this->requireType()
            ));
        }
    }
}
