<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Venue;
use TheatreCMS\Models\Work;

/**
 * Resolves a display title for any content entity, without callers needing to know that
 * a Production's title lives on `name`, a Season's on `label`, a Person's is composed
 * from first/last name, etc.
 *
 * This is the plain-PHP counterpart to `Twig\TitleExtension`'s `the_title()` function
 * (mirroring how `EditorJsHtmlConverter`/`EditorJsExtension` are split) so both Twig
 * templates and route code can resolve a title the same way — e.g. to build the generic
 * `page` context object every frontend route passes to its template.
 */
class TitleResolver
{
    public function resolve(mixed $entity): string
    {
        $title = match (true) {
            $entity instanceof Page => $entity->getTitle(),
            $entity instanceof Post => $entity->getTitle(),
            $entity instanceof Work => $entity->getTitle(),
            $entity instanceof Production => $entity->getName(),
            $entity instanceof Season => $entity->getLabel(),
            $entity instanceof Person => $entity->getName(),
            $entity instanceof Venue => $entity->getName(),
            $entity instanceof Sponsor => $entity->getName(),
            default => throw new \InvalidArgumentException(sprintf(
                'TitleResolver does not know how to resolve a title for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            )),
        };

        return (string) apply_filters('theatrecms/the_title', $title, $entity);
    }
}
