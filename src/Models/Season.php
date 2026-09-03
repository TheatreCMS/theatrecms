<?php

namespace TheatreCMS\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use TheatreCMS\Traits\HasSponsors;

#[Entity, Table(name: 'seasons')]
class Season extends ModelBase implements \JsonSerializable
{
    use HasSponsors;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id = 0;

    #[Column(type: 'string', nullable: false)]
    private string $label;

    #[Column(type: 'text', nullable: true)]
    private ?string $overview = null;

    #[ManyToOne(targetEntity: Image::class)]
    #[JoinColumn(name: 'featured_image_id', referencedColumnName: 'id', nullable: true)]
    private ?Image $featuredImage = null;

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

    public function getFeaturedImage(): ?Image
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?Image $featuredImage): self
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featuredImage?->getUrl();
    }

    public function hasFeaturedImage(): bool
    {
        return $this->featuredImage !== null;
    }

    public function getProductions(): Collection
    {
        return $this->productions;
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

    public function addProduction(Production $production): self
    {
        if (!$this->productions->contains($production)) {
            $this->productions->add($production);
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
