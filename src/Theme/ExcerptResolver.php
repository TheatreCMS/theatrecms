<?php

namespace TheatreCMS\Theme;

/**
 * Resolves the excerpt for any content entity that exposes `getExcerpt()`.
 *
 * This is the plain-PHP counterpart to `Twig\ExcerptExtension`'s `the_excerpt()`
 * function, mirroring `FeaturedImageResolver`/`Twig\FeaturedImageExtension` so theme
 * authors can resolve an excerpt without needing to know which model type they are
 * dealing with.
 */
class ExcerptResolver
{
    public function resolve(mixed $entity): string
    {
        if (!is_object($entity) || !method_exists($entity, 'getExcerpt')) {
            throw new \InvalidArgumentException(sprintf(
                'ExcerptResolver does not know how to resolve an excerpt for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            ));
        }

        $excerpt = (string) ($entity->getExcerpt() ?? '');

        return (string) apply_filters('theatrecms/the_excerpt', $excerpt, $entity);
    }
}
