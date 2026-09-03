<?php

namespace TheatreCMS\Models;

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
    #[ManyToOne(targetEntity: Production::class, inversedBy: 'people')]
    #[JoinColumn(name: 'production_id', referencedColumnName: 'id', nullable: false)]
    private Production $production;

    #[Id]
    #[ManyToOne(targetEntity: Person::class)]
    #[JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: false)]
    private Person $person;

    /**
     * The type of role (e.g., 'cast', 'production team', etc.)
     * @var RoleType|null
     */
    #[Id]
    #[Column(name: 'role_type', type: 'string', nullable: true, enumType: RoleType::class)]
    private ?RoleType $roleType = null;

    /**
     * The specific role (e.g., 'Hamlet', 'Director', etc.)
     * @var string|null
     */
    #[Column(type: 'string', nullable: true)]
    private ?string $role = null;

    /**
     * Display order of this person within their role type's list on the
     * production (e.g. the Performers list), lowest first.
     */
    #[Column(type: 'integer')]
    private int $position;

    public function __construct(Production $production, Person $person, int $position = 0)
    {
        $this->production = $production;
        $this->person = $person;
        $this->position = $position;
    }

    public function getProduction(): Production
    {
        return $this->production;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }


    public function getRoleType(): ?RoleType
    {
        return $this->roleType;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRoleType(?RoleType $roleType): self
    {
        $this->roleType = $roleType;

        return $this;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;

        return $this;
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
