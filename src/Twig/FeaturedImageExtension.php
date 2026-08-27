<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\FeaturedImageResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style `the_post_thumbnail_url()` corollary: a single function themes can call on
 * any content entity to get its featured image URL without needing to know which model type
 * it is, or whether it has one at all.
 *
 * The resolved URL is passed through the `theatrecms/the_featured_image_url` filter, mirroring
 * the rest of the theme helper stack exposed by this project.
 */
class FeaturedImageExtension extends AbstractExtension
{
    public function __construct(private readonly FeaturedImageResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_featured_image_url', [$this, 'theFeaturedImageUrl']),
        ];
    }

    public function theFeaturedImageUrl(mixed $entity): string
    {
        return $this->resolver->resolve($entity);
    }
}
