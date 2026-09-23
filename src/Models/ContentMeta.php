<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * A single key/value row of metadata attached to any content item, identified by
 * (content_type, content_id) rather than a dedicated FK column/table per content type.
 *
 * `content_type` is a free-form string (not a PHP enum) so that content types
 * registered by future theme/plugin extensions can use this table without code changes
 * here, matching this codebase's WordPress-inspired extensibility goals.
 */
#[Entity]
#[Table(name: 'content_meta')]
#[UniqueConstraint(name: 'uniq_content_meta_key', columns: ['content_type', 'content_id', 'meta_key'])]
class ContentMeta
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(name: 'content_type', type: 'string', length: 32, nullable: false)]
    private string $contentType;

    #[Column(name: 'content_id', type: 'integer', nullable: false)]
    private int $contentId;

    #[Column(name: 'meta_key', type: 'string', length: 191, nullable: false)]
    private string $metaKey;

    #[Column(name: 'meta_value', type: 'text', nullable: true)]
    private ?string $metaValue = null;

    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'modified_at', type: 'datetime_immutable')]
    private DateTimeImmutable $modifiedAt;

    public function __construct(string $contentType, int $contentId, string $metaKey, ?string $metaValue = null)
    {
        $this->contentType = $contentType;
        $this->contentId = $contentId;
        $this->metaKey = $metaKey;
        $this->metaValue = $metaValue;
        $this->createdAt = new DateTimeImmutable();
        $this->modifiedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getMetaKey(): string
    {
        return $this->metaKey;
    }

    public function getMetaValue(): ?string
    {
        return $this->metaValue;
    }

    public function setMetaValue(?string $metaValue): self
    {
        $this->metaValue = $metaValue;
        $this->touchModified();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function touchModified(): self
    {
        $this->modifiedAt = new DateTimeImmutable();

        return $this;
    }
}
