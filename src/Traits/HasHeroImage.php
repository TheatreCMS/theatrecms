<?php

namespace TheatreCMS\Traits;

/**
 * Hero image data lives in content_meta, not a Doctrine relation, so these properties
 * are not mapped — they're eager-populated by EagerLoadsHeroImage (repository trait)
 * on single-record fetches. heroImageUrl falls back to featuredImage (see
 * HasFeaturedImage) when no hero_image_id meta is set for this item; hasHeroImage
 * reflects only whether that meta value actually exists, independent of the fallback.
 */
trait HasHeroImage
{
    private ?string $heroImageUrl = null;

    private bool $hasHeroImage = false;

    public function getHeroImageUrl(): ?string
    {
        return $this->heroImageUrl;
    }

    public function setHeroImageUrl(?string $heroImageUrl): self
    {
        $this->heroImageUrl = $heroImageUrl;

        return $this;
    }

    public function hasHeroImage(): bool
    {
        return $this->hasHeroImage;
    }

    public function setHasHeroImage(bool $hasHeroImage): self
    {
        $this->hasHeroImage = $hasHeroImage;

        return $this;
    }
}
