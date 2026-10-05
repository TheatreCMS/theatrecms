<?php

namespace TheatreCMS\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;

/**
 * The shared creation/publication timestamps: mapped `created_at` and
 * `published_at` columns plus their accessors, for content types that are
 * published. `created_at` comes from HasCreatedTimestamp and is filled in on
 * persist; `published_at` stays whatever the content sets.
 */
trait HasTimestamps
{
    use HasCreatedTimestamp;

    #[Column(name: 'published_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $publishedAt = null;

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
