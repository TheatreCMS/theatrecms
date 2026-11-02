<?php

namespace TheatreCMS\ContentType;

use TheatreCMS\Models\Term;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

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
    public function __construct(
        private readonly ContentTypeRegistry $contentTypes,
        private readonly TaxonomyRegistry $taxonomies,
    ) {
    }

    /**
     * @throws \InvalidArgumentException for an entity that isn't content or a term, or whose type
     *         isn't public (it has no frontend routes, so no URL)
     */
    public function resolve(mixed $entity): string
    {
        return (string) apply_filters('theatrecms/the_permalink', $this->build($entity), $entity);
    }

    /**
     * The URL before filtering: a child's URL builds on its parent's unfiltered URL, so the
     * filter runs once, on the final URL.
     */
    private function build(mixed $entity): string
    {
        return match (true) {
            $entity instanceof Term => '/' . $this->taxonomies->require($entity->getTaxonomy())->urlPrefix
                . '/' . $entity->getSlug(),
            $entity instanceof IsHierarchical && $entity->hasParent() => $this->build($entity->getParent())
                . '/' . $this->publicSlug($entity),
            $entity instanceof HasContentType => '/' . ltrim(
                $this->contentTypes->require($entity->getContentType())->urlPrefix . '/' . $this->publicSlug($entity),
                '/'
            ),
            default => throw new \InvalidArgumentException(sprintf(
                'PermalinkResolver does not know how to resolve a URL for %s.',
                is_object($entity) ? get_class($entity) : get_debug_type($entity)
            )),
        };
    }

    /**
     * @throws \InvalidArgumentException when the entity's type isn't public
     */
    private function publicSlug(HasContentType $entity): string
    {
        $type = $entity->getContentType();

        if (!$this->contentTypes->require($type)->public) {
            throw new \InvalidArgumentException(
                sprintf('Content type "%s" is not public, so it has no permalinks.', $type)
            );
        }

        return $entity->getSlug();
    }

    /**
     * The listing/archive URL for a content type (e.g. `/shows` for `season` on a site
     * that rewrites the prefix). Pages have no archive — every other built-in type does.
     */
    public function archiveUrl(string $type): string
    {
        return '/' . $this->contentTypes->prefix($type);
    }
}
