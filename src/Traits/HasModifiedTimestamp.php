<?php

namespace TheatreCMS\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

/**
 * A mapped `modified_at` column, kept current automatically: set when the entity is first persisted
 * and refreshed whenever Doctrine flushes a change to it. No controller or repository needs to set it.
 *
 * The callbacks only run on entities marked `#[HasLifecycleCallbacks]`; see documentation/timestamps.md.
 */
trait HasModifiedTimestamp
{
    #[Column(name: 'modified_at', type: 'datetime_immutable')]
    private DateTimeImmutable $modifiedAt;

    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(DateTimeImmutable $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    /**
     * Marks the entity modified now. Flushing any change does this automatically; call it only to
     * record a modification that doesn't change a mapped field.
     */
    public function touchModified(): self
    {
        return $this->setModifiedAt(new DateTimeImmutable());
    }

    #[PrePersist]
    public function initializeModifiedTimestamp(): void
    {
        if (!isset($this->modifiedAt)) {
            $this->modifiedAt = new DateTimeImmutable();
        }
    }

    #[PreUpdate]
    public function refreshModifiedTimestamp(): void
    {
        $this->modifiedAt = new DateTimeImmutable();
    }
}
