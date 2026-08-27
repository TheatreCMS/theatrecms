<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;

/**
 * Resolves the canonical relative URL for any content entity, without callers needing
 * to know the entity's route shape (nested under a parent, flat, or prefix-less) or its
 * configured URL prefix (see `ContentTypeRegistry`).
 *
 * This is the plain-PHP counterpart to `Twig\PermalinkExtension`'s `the_permalink()`
 * function, mirroring `TitleResolver`/`SlugResolver`, so route code, `MenuItemResolver`,
 * and theme templates all build links the same way and stay correct if a site rewrites
 * a content type's URL prefix.
 */
class PermalinkResolver
{
    public function __construct(private readonly ContentTypeRegistry $contentTypes)
    {
    }

    public function resolve(mixed $entity): string
    {
        $url = match (true) {
            $entity instanceof Season => $this->archiveUrl('seasons') . '/' . $entity->getSlug(),
            $entity instanceof Production => $this->archiveUrl('seasons') . '/' . $entity->getSeason()->getSlug()
                . '/' . $entity->getSlug(),
            $entity instanceof Person => $this->archiveUrl('people') . '/' . $entity->getSlug(),
            $entity instanceof Work => $this->archiveUrl('works') . '/' . $entity->getSlug(),
            $entity instanceof Post => $this->archiveUrl('posts') . '/' . $entity->getSlug(),
            $entity instanceof Page => '/' . $entity->getSlug(),
            default => throw new \InvalidArgumentException(sprintf(
                'PermalinkResolver does not know how to resolve a URL for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            )),
        };

        return (string) apply_filters('theatrecms/the_permalink', $url, $entity);
    }

    /**
     * The listing/archive URL for a content type (e.g. `/shows` for `seasons` on a site
     * that rewrites the prefix). Pages have no archive — every other built-in type does.
     */
    public function archiveUrl(string $type): string
    {
        return '/' . $this->contentTypes->prefix($type);
    }
}
