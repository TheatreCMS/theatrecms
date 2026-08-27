<?php

namespace TheatreCMS\Traits;

use Doctrine\Common\Collections\Collection;
use TheatreCMS\Models\Sponsorship;

/**
 * The shared sponsorship accessors: `getSponsorships()`/`addSponsorship()`, reused by every
 * content type that can carry sponsors instead of each entity defining its own parallel pair
 * of methods.
 *
 * Composing classes must declare their own mapped `sponsorships` property (the `mappedBy` side
 * of `Sponsorship`'s association differs per entity, e.g. 'season' vs 'production', so it can't
 * be declared once here) and initialize it to an `ArrayCollection` themselves (typically in
 * their constructor) — this trait only supplies the accessors.
 */
trait HasSponsors
{
    public function getSponsorships(): Collection
    {
        return $this->sponsorships;
    }

    public function addSponsorship(Sponsorship $sponsorship): self
    {
        if (!$this->sponsorships->contains($sponsorship)) {
            $this->sponsorships->add($sponsorship);
        }

        return $this;
    }
}
