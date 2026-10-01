<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * Links a record in an external system (a WordPress post, a Tessitura production season, ...) to
 * the TheatreCMS content it was imported or synced into, so re-running an import finds the
 * existing content instead of creating a duplicate. See documentation/external-references.md.
 *
 * `content_id` is a soft reference, like `content_meta.content_id`: deleting the content doesn't
 * delete its references.
 */
#[Entity]
#[Table(name: 'external_references')]
#[UniqueConstraint(name: 'uniq_external_references_source', columns: ['source', 'source_type', 'source_id'])]
#[Index(name: 'idx_external_references_content', columns: ['content_type', 'content_id'])]
class ExternalReference
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    /** The external system, e.g. `wp` or `tessitura`. */
    #[Column(type: 'string', length: 32)]
    private string $source;

    /** The kind of record in that system, e.g. `post`, `attachment`, `production_season`. */
    #[Column(name: 'source_type', type: 'string', length: 64)]
    private string $sourceType;

    /** The record's ID in that system, as a string since not every system uses integers. */
    #[Column(name: 'source_id', type: 'string', length: 191)]
    private string $sourceId;

    /** The TheatreCMS content type, e.g. `production` (the same keys as term relationships). */
    #[Column(name: 'content_type', type: 'string', length: 32)]
    private string $contentType;

    #[Column(name: 'content_id', type: 'integer')]
    private int $contentId;

    /** When the content was last imported or synced from the external record. */
    #[Column(name: 'synced_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $syncedAt = null;

    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $source, string $sourceType, string $sourceId, string $contentType, int $contentId)
    {
        $this->source = $source;
        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
        $this->contentType = $contentType;
        $this->contentId = $contentId;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getSyncedAt(): ?DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function pointTo(string $contentType, int $contentId): self
    {
        $this->contentType = $contentType;
        $this->contentId = $contentId;

        return $this;
    }

    public function markSynced(?DateTimeImmutable $at = null): self
    {
        $this->syncedAt = $at ?? new DateTimeImmutable();

        return $this;
    }
}
