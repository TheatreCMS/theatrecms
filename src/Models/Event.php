<?php

namespace TheatreCMS\Models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity, Table(name: 'events')]
class Event extends ModelBase
{
    #[Id, Column(type: 'integer'), GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[ManyToOne(targetEntity: Production::class)]
    #[JoinColumn(name: 'production_id', referencedColumnName: 'id', nullable: true)]
    private ?Production $production = null;

    #[Column(name: 'starts_at', type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $startsAt;

    #[Column(name: 'ends_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[Column(type: 'string', nullable: false)]
    private string $status;

    #[Column(name: 'ticket_url', type: 'string', nullable: true)]
    private ?string $ticketUrl = null;

    #[Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ManyToOne(targetEntity: Venue::class)]
    #[JoinColumn(name: 'venue_id', referencedColumnName: 'id', nullable:true)]
    private ?Venue $venue = null;

    #[Column(type: 'string', nullable: true)]
    private ?string $title = null;

    public function __construct(\DateTimeImmutable $startsAt, string $status, ?Production $production = null, ?string $title = null)
    {
        $this->startsAt = $startsAt;
        $this->status = $status;
        $this->production = $production;
        $this->title = $title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProduction(): ?Production
    {
        return $this->production;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTicketUrl(): ?string
    {
        return $this->ticketUrl;
    }

    /**
     * The ticket URL to actually use for this performance: its own, if set,
     * otherwise the parent production's (e.g. when tickets for this specific
     * performance aren't sold separately).
     */
    public function getEffectiveTicketUrl(): ?string
    {
        return $this->ticketUrl ?: $this->production?->getTicketPurchaseUrl();
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getVenue(): ?Venue
    {
        return $this->venue;
    }

    public function setProduction(?Production $production): self
    {
        $this->production = $production;

        return $this;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setTicketUrl(?string $ticketUrl): self
    {
        $this->ticketUrl = $ticketUrl;

        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setVenue(?Venue $venue): self
    {
        $this->venue = $venue;

        return $this;
    }
}
