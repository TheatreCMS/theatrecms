<?php

namespace TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'sponsorships')]
class Sponsorship
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ManyToOne(targetEntity: Sponsor::class, inversedBy: 'sponsorships')]
    #[JoinColumn(name: 'sponsor_id', referencedColumnName: 'id', nullable: false)]
    private Sponsor $sponsor;

    #[ManyToOne(targetEntity: Season::class, inversedBy: 'sponsorships')]
    #[JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: true)]
    private ?Season $season = null;

    #[ManyToOne(targetEntity: Production::class, inversedBy: 'sponsorships')]
    #[JoinColumn(name: 'production_id', referencedColumnName: 'id', nullable: true)]
    private ?Production $production = null;

    public function __construct(Sponsor $sponsor)
    {
        $this->sponsor = $sponsor;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSponsor(): Sponsor
    {
        return $this->sponsor;
    }

    public function setSponsor(Sponsor $sponsor): self
    {
        $this->sponsor = $sponsor;
        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): self
    {
        $this->season = $season;
        return $this;
    }

    public function getProduction(): ?Production
    {
        return $this->production;
    }

    public function setProduction(?Production $production): self
    {
        $this->production = $production;
        return $this;
    }
}
