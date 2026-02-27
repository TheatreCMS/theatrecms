<?php


namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'work_creators')]
class WorkCreator extends ModelBase
{
    #[Id, Column(name: 'work_creator_id', type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    // Inversed by the Work::$workCreators collection
    #[ManyToOne(targetEntity: Work::class, inversedBy: 'workCreators')]
    #[JoinColumn(name: 'work_id', referencedColumnName: 'id', nullable: false)]
    private Work $work;

    #[ManyToOne(targetEntity: Person::class)]
    #[JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: false)]
    private Person $person;

    /**
     * The role of the creator in the work. E.g. "Author", "Composer", "Lyricist", etc.
     *
     * @var string
     */
    #[Column(type: 'string')]
    private string $role;

    public function __construct(Work $work, Person $person, string $role)
    {
        $this->work = $work;
        $this->person = $person;
        $this->role = $role;
    }

    public function work(): Work
    {
        return $this->work;
    }

    public function person(): Person
    {
        return $this->person;
    }

    public function role(): string
    {
        return $this->role;
    }
}
