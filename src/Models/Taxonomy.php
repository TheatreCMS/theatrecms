<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'taxonomies')]
class Taxonomy
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    protected int $id = 0;

    #[Column(type: 'string', nullable: false)]
    protected string $slug;

    #[Column(type: 'string', nullable: false)]
    protected string $label;

    #[Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    protected Collection $terms;

    public function getId(): int
    {
        return $this->id;
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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTerms(): Collection
    {
        return $this->terms;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
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

    public function addTerm($term): self
    {
        $this->terms->add($term);
        return $this;
    }
}