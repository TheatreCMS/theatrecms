<?php
namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'works')]
class Work {

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $title;

    #[Column(type: 'text', nullable: true)]
    private string $description;

    /**
     * Many works have many creators.
     * @var Collection<int, Person>
     */
    #[JoinTable(name: 'work_creators')]
    #[JoinColumn(name: 'work_id', referencedColumnName: 'id')]
    #[InverseJoinColumn(name: 'person_id', referencedColumnName: 'id', unique: true)]
    #[ManyToMany(targetEntity: Person::class)]
    private Collection $creators;

    public function getId(): int {
        return $this->id;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getDescription(): string {
        return $this->description;
    }

    /**
     * @return Collection<int, Person>
     */
    public function getCreators(): Collection {
        return $this->creators;
    }

    public function addCreator($creator): self {
        if (!$this->creators->contains($creator)) {
            $this->creators->add($creator);
        }
        return $this;
    }

    public function setTitle(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }
}
