<?php

namespace TheatreCMS\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\PrePersist;

/**
 * A mapped `created_at` column, filled in automatically when the entity is first persisted.
 *
 * The callback only runs on entities marked `#[HasLifecycleCallbacks]`; see documentation/timestamps.md.
 */
trait HasCreatedTimestamp
{
    #[Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[PrePersist]
    public function initializeCreatedTimestamp(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new DateTimeImmutable();
        }
    }
}
