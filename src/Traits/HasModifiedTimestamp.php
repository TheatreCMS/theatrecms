<?php

namespace TheatreCMS\Traits;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;

/**
 * The shared mutation-tracking timestamp: a mapped `modified_at` column
 * plus its accessors, reused by every content type that needs to track
 * when it was last edited instead of each entity defining its own
 * parallel field.
 *
 * Composing classes must set `modifiedAt` themselves (typically in their
 * constructor) — this trait does not assume a default.
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

    public function touchModified(): self
    {
        return $this->setModifiedAt(new DateTimeImmutable());
    }
}
