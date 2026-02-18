<?php

namespace Clubdeuce\TheatreCMS\Models;

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

#[Entity, Table(name: 'productions')]
class Production
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $name;

    #[Column(type: 'text', nullable: true)]
    private string $description;

    #[Column(type: 'string', nullable: true)]
    private string $excerpt;

    /**
     * @var int Runtime in minutes
     */
    #[Column(type: 'integer', nullable: true)]
    private int $runtime;

    #[Column(name: 'age_recommendation', type: 'string', nullable: true)]
    private string $ageRecommendation;

    #[Column(name: 'content_advisory', type: 'text', nullable: true)]
    private string $contentAdvisory;

    private Collection $people;
    private Collection $creativeTeam;
    private Collection $performers;

    #[Column(name: 'promo_video_url', type: 'string', nullable: true)]
    private string $promoVideoUrl;

    #[Column(name: 'ticket_purchase_url', type: 'string', nullable: true)]
    private string $ticketPurchaseUrl;


    #[ManyToOne(targetEntity: Season::class, inversedBy: 'productions')]
    #[JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: false)]
    private Season $season;

    public function __construct(string $name, Season $season)
    {
        $this->name   = $name;
        $this->season = $season;
        $this->people = new ArrayCollection();
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function getRuntime(): int
    {
        return $this->runtime;
    }

    public function getAgeRecommendation(): string
    {
        return $this->ageRecommendation;
    }

    public function getContentAdvisory(): string
    {
        return $this->contentAdvisory;
    }

    public function getCreativeTeam(): Collection
    {
        return new ArrayCollection();
    }

    public function getPerformers(): Collection
    {
        return new ArrayCollection();
    }

    public function getPromoVideoUrl(): string
    {
        return $this->promoVideoUrl;
    }

    public function getTicketPurchaseUrl(): string
    {
        return $this->ticketPurchaseUrl;
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

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setExcerpt(string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function setRuntime(int $runtime): self
    {
        $this->runtime = $runtime;

        return $this;
    }

    public function setAgeRecommendation(string $ageRecommendation): self
    {
        $this->ageRecommendation = $ageRecommendation;

        return $this;
    }

    public function setContentAdvisory(string $contentAdvisory): self
    {
        $this->contentAdvisory = $contentAdvisory;

        return $this;
    }

    public function setPromoVideoUrl(string $promoVideoUrl): self
    {
        $this->promoVideoUrl = $promoVideoUrl;

        return $this;
    }

    public function setTicketPurchaseUrl(string $ticketPurchaseUrl): self
    {
        $this->ticketPurchaseUrl = $ticketPurchaseUrl;

        return $this;
    }
}
