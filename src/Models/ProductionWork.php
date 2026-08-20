<?php

namespace TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'production_works')]
class ProductionWork
{
    #[Id]
    #[ManyToOne(targetEntity: Production::class, inversedBy: 'productionWorks')]
    #[JoinColumn(name: 'production_id', referencedColumnName: 'id', nullable: false)]
    private Production $production;

    #[Id]
    #[ManyToOne(targetEntity: Work::class)]
    #[JoinColumn(name: 'work_id', referencedColumnName: 'id', nullable: false)]
    private Work $work;

    /**
     * Display order of this work within the production's works list (e.g. a
     * choir's setlist order), lowest first.
     */
    #[Column(type: 'integer')]
    private int $position;

    public function __construct(Production $production, Work $work, int $position = 0)
    {
        $this->production = $production;
        $this->work = $work;
        $this->position = $position;
    }

    public function getProduction(): Production
    {
        return $this->production;
    }

    public function getWork(): Work
    {
        return $this->work;
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
