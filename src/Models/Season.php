<?php

namespace TheatreCMS\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'seasons')]
class Season extends ModelBase implements \JsonSerializable
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id = 0;

    #[Column(type: 'string', nullable: false)]
    private string $label;

    #[Column(type: 'text', nullable: true)]
    private ?string $overview = null;

    #[Column(name: 'featured_image_url', type: 'string', nullable: true)]
    private ?string $featuredImageUrl = null;

    #[Column(name: 'start_date', type: 'datetime', nullable: false)]
    private ?\DateTime $startDate = null;

    #[Column(name: 'end_date', type: 'datetime', nullable: false)]
    private ?\DateTime $endDate = null;
    
    #[OneToMany(targetEntity: Production::class, mappedBy: 'season', cascade: ['persist', 'remove'])]
    private Collection $productions;

    #[OneToMany(mappedBy: 'season', targetEntity: Sponsorship::class)]
    private Collection $sponsorships;


    public function __construct(string $slug, string $label)
    {
        // ModelBase does not define a constructor; set slug via setter
        $this->setSlug($slug);

        $this->label       = $label;
        $this->productions = new ArrayCollection();
        $this->sponsorships = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getOverview(): string
    {
        return $this->overview ?? '';
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featuredImageUrl;
    }

    public function getProductions(): Collection
    {
        return $this->productions;
    }

    public function getSponsorships(): Collection
    {
        return $this->sponsorships;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function setOverview(?string $overview): self
    {
        $this->overview = $overview;

        return $this;
    }

    public function setStartDate(\DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function setEndDate(\DateTime $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function setFeaturedImageUrl(?string $featuredImageUrl): self
    {
        $this->featuredImageUrl = $featuredImageUrl;

        return $this;
    }

    public function addProduction(Production $production): self
    {
        if (!$this->productions->contains($production)) {
            $this->productions->add($production);
        }

        return $this;
    }

    public function addSponsorship(Sponsorship $sponsorship): self
    {
        if (!$this->sponsorships->contains($sponsorship)) {
            $this->sponsorships->add($sponsorship);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'slug' => $this->getSlug(),
            'label' => $this->getLabel(),
            'startDate' => $this->getStartDate()?->format('Y-m-d'),
            'endDate' => $this->getEndDate()?->format('Y-m-d'),
            'overview' => $this->getOverview(),
            'featuredImageUrl' => $this->getFeaturedImageUrl(),
            'productions' => $this->getProductions()->toArray(),
        ];
    }
}
