<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Season;

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
            $entity instanceof(Season::class) => $entity->getStartDate(),
            default => $entity->getPublishedAt() ?? $entity->getCreatedAt()
        };

        $resolvedDate = $date->format($format);

        return (string) apply_filters('theatrecms/the_date', $resolvedDate, $entity, $date, $format);
    }
}