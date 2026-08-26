<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Post;

/**
 * Resolves a display date string for content entities.
 *
 * Currently supports posts, returning the published date when available and
 * falling back to created-at for drafts.
 */
class DateResolver
{
    public function resolve(mixed $entity, string $format = 'F j, Y'): string
    {
        $date = match (true) {
            $entity instanceof Post => $entity->getPublishedAt() ?? $entity->getCreatedAt(),
            default => throw new \InvalidArgumentException(sprintf(
                'DateResolver does not know how to resolve a date for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            )),
        };

        $resolvedDate = $date->format($format);

        return (string) apply_filters('theatrecms/the_date', $resolvedDate, $entity, $date, $format);
    }
}