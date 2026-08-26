<?php

namespace TheatreCMS\Theme;

/**
 * Resolves the slug for any content entity that exposes `getSlug()`.
 *
 * This is the plain-PHP counterpart to `Twig\SlugExtension`'s `the_slug()` function,
 * mirroring `TitleResolver`/`Twig\TitleExtension` so theme authors can resolve a
 * stable slug without needing to know which model type they are dealing with.
 */
class SlugResolver
{
    public function resolve(mixed $entity): string
    {
        if (!is_object($entity) || !method_exists($entity, 'getSlug')) {
            throw new \InvalidArgumentException(sprintf(
                'SlugResolver does not know how to resolve a slug for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            ));
        }

        $slug = (string) $entity->getSlug();

        return (string) apply_filters('theatrecms/the_slug', $slug, $entity);
    }
}
