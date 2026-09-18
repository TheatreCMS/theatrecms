<?php

namespace TheatreCMS\Traits;

use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use TheatreCMS\Models\Media;

/**
 * A single "featured image" relation, shown wherever a content item is referenced
 * outside its own single page (cards, listings, links) — not on the single page itself.
 * See HasHeroImage for the counterpart shown only on the single page.
 */
trait HasFeaturedImage
{
    #[ManyToOne(targetEntity: Media::class)]
    #[JoinColumn(name: 'featured_image_id', referencedColumnName: 'id', nullable: true)]
    private ?Media $featuredImage = null;

    public function getFeaturedImage(): ?Media
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?Media $featuredImage): self
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->featuredImage?->getUrl();
    }

    public function hasFeaturedImage(): bool
    {
        return $this->featuredImage !== null;
    }
}
