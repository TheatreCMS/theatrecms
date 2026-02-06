<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'productions')]
class Production
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $name;

    #[ManyToOne(targetEntity: Season::class)]
    #[JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: false)]
    private Season $season;

    public function __construct(string $name, Season $season)
    {
        $this->name = $name;
        $this->season = $season;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setSeason(Season $season): self
    {
        $this->season = $season;

        return $this;
    }
}
