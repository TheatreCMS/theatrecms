<?php

namespace Clubdeuce\TheatreCMS\Traits;

use Doctrine\ORM\Mapping\Column;

trait SupportsFeaturedImage
{
    #[Column(name: 'featured_image_url', type: 'string', nullable: true)]
    private ?string $featuredImageUrl = null;

    public function getFeaturedImageUrl(): string
    {
        return $this->featuredImageUrl ?? '';
    }

    public function hasFeaturedImage(): bool
    {
        return $this->featuredImageUrl !== null && $this->featuredImageUrl !== '';
    }

    public function setFeaturedImageUrl(?string $featuredImageUrl): self
    {
        $this->featuredImageUrl = $featuredImageUrl;

        return $this;
    }
}
