<?php

namespace TheatreCMS\Theme;

/**
 * Resolves a comma-separated list of sponsor names for any content entity that exposes
 * `getSponsorships()` (currently `Season` and `Production`, via the shared `Traits\HasSponsors`
 * trait).
 *
 * This is the plain-PHP counterpart to `Twig\SponsorsExtension`'s `the_sponsors()` function,
 * mirroring `FeaturedImageResolver`/`Twig\FeaturedImageExtension` so theme authors can resolve
 * sponsor names without needing to know which model type they are dealing with.
 */
class SponsorsResolver
{
    public function resolve(mixed $entity): string
    {
        if (!is_object($entity) || !method_exists($entity, 'getSponsorships')) {
            throw new \InvalidArgumentException(sprintf(
                'SponsorsResolver does not know how to resolve sponsors for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            ));
        }

        $names = [];
        foreach ($entity->getSponsorships() as $sponsorship) {
            $names[] = $sponsorship->getSponsor()->getName();
        }

        $names = array_values(array_unique($names));
        $list = implode(', ', $names);

        return (string) apply_filters('theatrecms/the_sponsors', $list, $entity, $names);
    }
}
