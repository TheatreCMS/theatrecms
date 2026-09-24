<?php

namespace TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * Assigns a Term to any content item, identified by (content_type, content_id) rather than
 * a dedicated join table per content type — same approach as ContentMeta.
 *
 * `content_id` is a soft reference (no FK), since it points at a different table depending
 * on `content_type`. `term_id` is a real FK that cascades when the term is deleted.
 */
#[Entity]
#[Table(name: 'term_relationships')]
#[UniqueConstraint(name: 'uniq_term_rel', columns: ['term_id', 'content_type', 'content_id'])]
#[Index(name: 'idx_term_rel_content', columns: ['content_type', 'content_id'])]
class TermRelationship
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ManyToOne(targetEntity: Term::class)]
    #[JoinColumn(name: 'term_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Term $term;

    #[Column(name: 'content_type', type: 'string', length: 32, nullable: false)]
    private string $contentType;

    #[Column(name: 'content_id', type: 'integer', nullable: false)]
    private int $contentId;

    /**
     * Assignment order within a taxonomy; the first term is treated as the item's primary one.
     */
    #[Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    public function __construct(Term $term, string $contentType, int $contentId, int $position = 0)
    {
        $this->term = $term;
        $this->contentType = $contentType;
        $this->contentId = $contentId;
        $this->position = $position;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTerm(): Term
    {
        return $this->term;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentId(): int
    {
        return $this->contentId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }
}
