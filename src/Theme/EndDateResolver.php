<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;

/**
 * This class is used to generate the end date for either seasons or productions.
 */
class EndDateResolver
{
    public function resolve(mixed $entity): ?\DateTime
    {
        return match (true) {
            $entity instanceof Season     => $entity->getEndDate(),
            $entity instanceof Production => $entity->getClosing(),
            default => null,
        };
    }
}
