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
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\Table;
use DateTime;
use TheatreCMS\Traits\HasSponsors;
use TheatreCMS\Traits\SupportsFeaturedImage;

#[Entity, Table(name: 'productions')]
class Production extends ModelBase
{
    use SupportsFeaturedImage;
    use HasSponsors;

    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $name;

    #[Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[Column(type: 'string', nullable: true)]
    private ?string $excerpt = null;

    #[Column(type: 'date', nullable: true)]
    private ?DateTime $opening = null;

    #[Column(type: 'date', nullable: true)]
    private ?DateTime $closing = null;

    /**
     * @var int Runtime in minutes
     */
    #[Column(type: 'integer', nullable: true)]
    private ?int $runtime = null;

    #[Column(name: 'age_recommendation', type: 'string', nullable: true)]
    private ?string $ageRecommendation = null;

    #[Column(name: 'content_advisory', type: 'text', nullable: true)]
    private ?string $contentAdvisory = null;

    #[OneToMany(targetEntity: ProductionPerson::class, mappedBy: 'production', cascade: ['persist', 'remove'])]
    private Collection $people;

    #[OneToMany(targetEntity: Event::class, mappedBy: 'production', cascade: ['persist', 'remove'])]
    private Collection $performances;

    #[Column(name: 'promo_video_url', type: 'string', nullable: true)]
    private string $promoVideoUrl;

    #[Column(name: 'ticket_purchase_url', type: 'string', nullable: true)]
    private string $ticketPurchaseUrl;

    #[ManyToOne(targetEntity: Season::class, inversedBy: 'productions')]
    #[JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: false)]
    private Season $season;

    #[ManyToOne(targetEntity: Venue::class, inversedBy: 'productions')]
    #[JoinColumn(name: 'venue_id', referencedColumnName: 'id', nullable: true)]
    private ?Venue $venue = null;

    // Many productions have many works, in a user-defined display order (e.g. a choir's setlist).
    #[OneToMany(targetEntity: ProductionWork::class, mappedBy: 'production', cascade: ['persist', 'remove'])]
    #[OrderBy(['position' => 'ASC'])]
    private Collection $productionWorks;

    #[OneToMany(targetEntity: Sponsorship::class, mappedBy: 'production', cascade: ['persist', 'remove'])]
    private Collection $sponsorships;

    /**
     * Accept either a single Work instance, an array of Work instances, or null for $works.
     * This keeps compatibility with existing unit tests which pass a Work as the third arg.
     *
     * @param string $name
     * @param Season $season
     * @param Work|Work[]|null $works
     */
    public function __construct(string $name, Season $season, $works = null)
    {
        $this->name            = $name;
        $this->season          = $season;
        $this->productionWorks = new ArrayCollection();
        $this->people          = new ArrayCollection();
        $this->sponsorships    = new ArrayCollection();
        $this->performances    = new ArrayCollection();

        if ($works instanceof Work) {
            $this->addWork($works);
        } elseif (is_array($works)) {
            foreach ($works as $w) {
                if ($w instanceof Work) {
                    $this->addWork($w);
                }
            }
        }
    }

    public function getOpening(): ?DateTime
    {
        return $this->opening;
    }

    public function setOpening(?DateTime $opening): self
    {
        $this->opening = $opening;

        return $this;
    }

    public function getClosing(): ?DateTime
    {
        return $this->closing;
    }

    public function setClosing(?DateTime $closing): self
    {
        $this->closing = $closing;

        return $this;
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
        return $this->description ?? '';
    }

    public function getExcerpt(): string
    {
        return $this->excerpt ?? '';
    }

    public function getRuntime(): int
    {
        return $this->runtime ?? 0;
    }

    public function getAgeRecommendation(): string
    {
        return $this->ageRecommendation ?? '';
    }

    public function getContentAdvisory(): string
    {
        return $this->contentAdvisory ?? '';
    }

    public function getCreativeTeam(): Collection
    {
        return $this->people->filter(fn(ProductionPerson $productionPerson) => $productionPerson->getRoleType() === RoleType::Creative);
    }

    public function getPerformers(): Collection
    {
        return $this->people->filter(fn(ProductionPerson $productionPerson) => $productionPerson->getRoleType() === RoleType::Cast);
    }

    public function getProductionTeam(): Collection
    {
        return $this->people->filter(fn(ProductionPerson $productionPerson) => $productionPerson->getRoleType() === RoleType::ProductionTeam);
    }

    public function getPromoVideoUrl(): string
    {
        return $this->promoVideoUrl ?? '';
    }

    public function getTicketPurchaseUrl(): string
    {
        return $this->ticketPurchaseUrl ?? '';
    }

    public function getPeople(): Collection
    {
        return $this->people;
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

    public function getVenue(): ?Venue
    {
        return $this->venue;
    }

    public function setVenue(?Venue $venue): self
    {
        $this->venue = $venue;

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

    public function addToCreativeTeam(Person $person, ?string $role = null): self
    {
        $productionPerson = new ProductionPerson($this, $person);
        $productionPerson->setRoleType(RoleType::Creative);
        $productionPerson->setRole($role);
        $this->people->add($productionPerson);

        return $this;
    }

    public function addPerformer(Person $person, ?string $role = null): self
    {
        $productionPerson = new ProductionPerson($this, $person);
        $productionPerson->setRoleType(RoleType::Cast);
        $productionPerson->setRole($role);
        $this->people->add($productionPerson);

        return $this;
    }

    public function addToProductionTeam(Person $person, ?string $role = null): self
    {
        $productionPerson = new ProductionPerson($this, $person);
        $productionPerson->setRoleType(RoleType::ProductionTeam);
        $productionPerson->setRole($role);
        $this->people->add($productionPerson);

        return $this;
    }

    public function getProductionWorks(): Collection
    {
        return $this->productionWorks;
    }

    /**
     * @return Collection<int, Work> the assigned works, in display order
     */
    public function getWorks(): Collection
    {
        return $this->productionWorks->map(fn(ProductionWork $productionWork) => $productionWork->getWork());
    }

    public function addWork(Work $work, ?int $position = null): self
    {
        foreach ($this->productionWorks as $productionWork) {
            if ($productionWork->getWork() === $work) {
                return $this;
            }
        }

        $this->productionWorks->add(new ProductionWork($this, $work, $position ?? $this->productionWorks->count()));

        return $this;
    }

    public function setWorks(array $works): self
    {
        $this->productionWorks->clear();

        foreach ($works as $work) {
            if ($work instanceof Work) {
                $this->addWork($work);
            }
        }

        return $this;
    }

    public function getPerformances(): Collection
    {
        return $this->performances;
    }
}
