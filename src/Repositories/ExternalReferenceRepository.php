<?php

namespace TheatreCMS\Repositories;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use TheatreCMS\Models\ExternalReference;

/**
 * Lookups between external records and TheatreCMS content, in both directions. Deliberately does
 * not extend BaseRepository: references are keyed by (source, source type, source ID), not listed
 * or paginated in the admin.
 */
class ExternalReferenceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $source, string $sourceType, string $sourceId): ?ExternalReference
    {
        return $this->em->getRepository(ExternalReference::class)->findOneBy([
            'source' => $source,
            'sourceType' => $sourceType,
            'sourceId' => $sourceId,
        ]);
    }

    /**
     * Links an external record to content, creating the reference or repointing an existing one,
     * and marks it synced.
     */
    public function link(
        string $source,
        string $sourceType,
        string $sourceId,
        string $contentType,
        int $contentId,
        ?DateTimeImmutable $syncedAt = null,
    ): ExternalReference {
        $reference = $this->find($source, $sourceType, $sourceId);
        if ($reference === null) {
            $reference = new ExternalReference($source, $sourceType, $sourceId, $contentType, $contentId);
            $this->em->persist($reference);
        } else {
            $reference->pointTo($contentType, $contentId);
        }

        $reference->markSynced($syncedAt);
        $this->em->flush();

        return $reference;
    }

    /**
     * Every external record linked to a piece of content (it may have one per source), ordered
     * by source, source type and source ID.
     *
     * @return ExternalReference[]
     */
    public function forContent(string $contentType, int $contentId): array
    {
        return $this->em->getRepository(ExternalReference::class)->findBy(
            ['contentType' => $contentType, 'contentId' => $contentId],
            ['source' => 'ASC', 'sourceType' => 'ASC', 'sourceId' => 'ASC'],
        );
    }

    /**
     * Records that an external record was synced again without changing what it points to.
     *
     * @throws InvalidArgumentException when no reference exists for the record
     */
    public function touch(string $source, string $sourceType, string $sourceId, ?DateTimeImmutable $at = null): ExternalReference
    {
        $reference = $this->find($source, $sourceType, $sourceId);
        if ($reference === null) {
            throw new InvalidArgumentException(sprintf(
                'No external reference for %s %s "%s".',
                $source,
                $sourceType,
                $sourceId,
            ));
        }

        $reference->markSynced($at);
        $this->em->flush();

        return $reference;
    }

    /**
     * Removes the references to a piece of content. Content deletion doesn't do this automatically
     * (content_id is a soft reference), so call it when deleting content that may have been
     * imported or synced; see documentation/external-references.md.
     *
     * @return int the number of references removed
     */
    public function deleteForContent(string $contentType, int $contentId): int
    {
        $references = $this->forContent($contentType, $contentId);
        foreach ($references as $reference) {
            $this->em->remove($reference);
        }
        $this->em->flush();

        return count($references);
    }
}
