<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'production_people')]
class ProductionPerson
{
    #[Id]
    #[ManyToOne(targetEntity: Production::class)]
    #[JoinColumn(name: 'production_id', referencedColumnName: 'id', nullable: false)]
    private Production $production;

    #[Id]
    #[ManyToOne(targetEntity: Person::class)]
    #[JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: false)]
    private Person $person;

    /**
     * The type of role (e.g., 'cast', 'production team', etc.)
     * @var string|null
     */
    #[Column(name: 'role_type', type: 'text', nullable: true)]
    private ?string $roleType = null;

    /**
     * The specific role (e.g., 'Hamlet', 'Director', etc.)
     * @var string|null
     */
    #[Column(type: 'string', nullable: true)]
    private ?string $role = null;

    public function __construct(Production $production, Person $person)
    {
        $this->production = $production;
        $this->person = $person;
    }

    public function getProduction(): Production
    {
        return $this->production;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }


    public function getRoleType(): ?string
    {
        return $this->roleType;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;

        return $this;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;

        return $this;
    }
}
