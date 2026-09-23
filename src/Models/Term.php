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
use TheatreCMS\Traits\HasModifiedTimestamp;

/**
 * A single term (e.g. "Comedy") within a registered taxonomy (e.g. `genre`).
 *
 * Deliberately does not extend ModelBase: its slug column is unique across the whole
 * table, whereas term slugs only need to be unique within their taxonomy.
 */
#[Entity]
#[Table(name: 'terms')]
#[UniqueConstraint(name: 'uniq_terms_taxonomy_slug', columns: ['taxonomy', 'slug'])]
#[Index(name: 'idx_terms_taxonomy', columns: ['taxonomy'])]
class Term
{
    use HasModifiedTimestamp;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    /**
     * Registry key of the taxonomy this term belongs to. A free-form string rather than an
     * enum so theme/plugin-registered taxonomies need no code changes here.
     */
    #[Column(type: 'string', length: 32, nullable: false)]
    private string $taxonomy;

    #[Column(type: 'string', length: 255, nullable: false)]
    private string $name;

    #[Column(type: 'string', length: 191, nullable: false)]
    private string $slug;

    #[Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $taxonomy, string $name, string $slug)
    {
        $this->taxonomy = $taxonomy;
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new DateTimeImmutable();
        $this->modifiedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTaxonomy(): string
    {
        return $this->taxonomy;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
