<?php

namespace Clubdeuce\TheatreCMS\Models;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'seasons')]
class Season implements \JsonSerializable
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id = 0;

    #[Column(type: 'string', nullable: false)]
    private string $slug;

    #[Column(type: 'string', nullable: false)]
    private string $label;

    #[Column(type: 'text', nullable: true)]
    private ?string $overview = null;

    #[Column(name: 'hero_image_url', type: 'string', nullable: false)]
    private string $heroImageUrl = '';
    
    #[Column(name: 'start_date', type: 'datetime', nullable: false)]
    private ?\DateTime $startDate = null;

    #[Column(name: 'end_date', type: 'datetime', nullable: false)]
    private ?\DateTime $endDate = null;

    private Collection $productions;

    public function __construct(string $slug, string $label)
    {
        $this->slug = $slug;
        $this->label = $label;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
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

    public function getHeroImageUrl(): string
    {
        return $this->heroImageUrl;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
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

    public function setHeroImageUrl(string $heroImageUrl): self
    {
        $this->heroImageUrl = $heroImageUrl;

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
            'heroImageUrl' => $this->getHeroImageUrl(),
        ];
    }
}
