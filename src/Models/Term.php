<?php
namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'terms')]
class Term
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    protected int $id;

    #[Column(type: 'string', nullable: false)]
    protected string $name;

    #[Column(type: 'string', nullable: false)]
    protected string $slug;

    #[Column(type: 'text', nullable: true)]
    protected string $description;

    #[ManyToOne(targetEntity: Taxonomy::class, inversedBy: 'terms')]
    #[JoinColumn(name: 'taxonomy_id', referencedColumnName: 'id', nullable: false)]
    protected int $taxonomyId;

    public function getId(): int
    {
        return $this->id;
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

}
