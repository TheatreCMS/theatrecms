<?php

namespace TheatreCMS\Theme;

use TheatreCMS\Models\Page;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Post;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;
use TheatreCMS\Text\EditorJsHtmlConverter;

/**
 * Resolves rendered HTML content for any content entity, without callers needing to know
 * that a Page/Post/Production/Work/Season's content is Editor.js JSON (and must go through
 * `EditorJsHtmlConverter`) while a Person's biography is already-sanitized HTML.
 *
 * This is the plain-PHP counterpart to `Twig\ContentExtension`'s `the_content()` function,
 * mirroring `TitleResolver`/`Twig\TitleExtension`'s split for `the_title()`.
 */
class ContentResolver
{
    public function __construct(private readonly EditorJsHtmlConverter $converter)
    {
    }

    public function resolve(mixed $entity): string
    {
        $html = match (true) {
            $entity instanceof Page => $this->converter->toHtml($entity->getContent()),
            $entity instanceof Post => $this->converter->toHtml($entity->getContent()),
            $entity instanceof Production => $this->converter->toHtml($entity->getDescription()),
            $entity instanceof Work => $this->converter->toHtml($entity->getDescription()),
            $entity instanceof Season => $this->converter->toHtml($entity->getOverview()),
            $entity instanceof Person => $entity->getBiography(),
            default => throw new \InvalidArgumentException(sprintf(
                'ContentResolver does not know how to resolve content for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            )),
        };

        return (string) apply_filters('theatrecms/the_content', $html, $entity);
    }
}
