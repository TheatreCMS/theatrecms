<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Venue;

/**
 * Resolves a venue's mailing address in the typical United States two-line format:
 *
 *   123 Main St
 *   Columbus, OH 43215
 *
 * This is the plain-PHP counterpart to `Twig\AddressExtension`'s `the_address()`
 * function, mirroring the rest of the theme helper stack exposed by this project.
 */
class AddressResolver
{
    public function resolve(mixed $entity): string
    {
        if (!$entity instanceof Venue) {
            throw new \InvalidArgumentException(sprintf(
                'AddressResolver does not know how to resolve an address for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            ));
        }

        $addressLine = trim($entity->getAddress());

        $cityState = implode(', ', array_filter(
            [trim($entity->getCity()), trim($entity->getState())],
            static fn(string $part): bool => $part !== ''
        ));
        $cityStateZip = trim($cityState . ' ' . trim($entity->getPostcode()));

        $lines = array_filter([$addressLine, $cityStateZip], static fn(string $line): bool => $line !== '');

        return (string) apply_filters('theatrecms/the_address', implode("\n", $lines), $entity);
    }
}
