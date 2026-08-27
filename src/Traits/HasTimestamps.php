<?php

namespace TheatreCMS\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;

/**
 * The shared creation/publication timestamps: mapped `created_at` and
 * `published_at` columns plus their accessors, reused by every content
 * type that needs them instead of each entity defining its own parallel
 * fields.
 *
 * Composing classes must set `createdAt` themselves (typically in their
 * constructor) — this trait does not assume a default.
 */
trait HasTimestamps
{
    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }
}
