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
            $entity instanceof (Season::class) => $entity->getStartDate(),
            default => $entity->getPublishedAt() ?? $this->resolveCreatedAt($entity)
        };

        $resolvedDate = $date instanceof \DateTimeInterface ? $date->format($format) : '';

        return (string) apply_filters('theatrecms/the_date', $resolvedDate, $entity, $date, $format);
    }

    /**
     * getCreatedAt() is only implemented by entities using HasTimestamps (e.g.
     * Post); entities without any creation/publish timestamp at all (e.g. an
     * unscheduled Production with no opening date) simply have no date to show.
     */
    private function resolveCreatedAt(mixed $entity): ?\DateTimeInterface
    {
        return method_exists($entity, 'getCreatedAt') ? $entity->getCreatedAt() : null;
    }
}
