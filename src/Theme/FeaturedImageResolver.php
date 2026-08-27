<?php

namespace TheatreCMS\Theme;

/**
 * Resolves the featured image URL for any content entity that exposes `getFeaturedImageUrl()`.
 *
 * This is the plain-PHP counterpart to `Twig\FeaturedImageExtension`'s `the_featured_image_url()`
 * function, mirroring `SlugResolver`/`Twig\SlugExtension` so theme authors can resolve a
 * featured image URL without needing to know which model type they are dealing with.
 */
class FeaturedImageResolver
{
    public function resolve(mixed $entity): string
    {
        if (!is_object($entity) || !method_exists($entity, 'getFeaturedImageUrl')) {
            throw new \InvalidArgumentException(sprintf(
                'FeaturedImageResolver does not know how to resolve a featured image for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            ));
        }

        $url = (string) ($entity->getFeaturedImageUrl() ?? '');

        return (string) apply_filters('theatrecms/the_featured_image_url', $url, $entity);
    }
}
