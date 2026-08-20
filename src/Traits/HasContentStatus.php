<?php

namespace TheatreCMS\Traits;

use Doctrine\ORM\Mapping\Column;
use TheatreCMS\Enums\ContentStatus;

/**
 * The shared editorial status (draft/published) engine: one mapped `status`
 * column plus its accessors, reused by every content type that needs a
 * publication workflow instead of each entity defining its own parallel
 * status field/enum. Currently used by Post and Page; Seasons, Productions,
 * and Events are expected to adopt this same trait.
 *
 * Composing classes must set an initial status themselves (typically in
 * their constructor) — this trait does not assume a default.
 */
trait HasContentStatus
{
    #[Column(type: 'string', length: 32, nullable: false, enumType: ContentStatus::class)]
    private ContentStatus $status;

    public function getStatus(): ContentStatus
    {
        return $this->status;
    }

    public function setStatus(ContentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::PUBLISHED;
    }
}
