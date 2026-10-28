<?php

namespace TheatreCMS\Traits;

use Doctrine\ORM\Mapping\Column;

/**
 * A mapped `excerpt` column: a short plain-text summary shown in listings and as the fallback
 * SEO description. `getExcerpt()` returns the stored value unfiltered, which is what the admin
 * edits; frontend output goes through `ExcerptResolver` (`the_excerpt()`), which applies the
 * `theatrecms/the_excerpt` filter.
 */
trait HasExcerpt
{
    #[Column(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }
}
