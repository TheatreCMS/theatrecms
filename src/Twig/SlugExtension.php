<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\SlugResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style `the_slug()` corollary: a single function themes can call on any
 * content entity to get the stable slug without needing to know which model class it is.
 *
 * The resolved slug is passed through the `theatrecms/the_slug` filter, mirroring
 * the rest of the theme helper stack exposed by this project.
 */
class SlugExtension extends AbstractExtension
{
    public function __construct(private readonly SlugResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_slug', [$this, 'theSlug']),
        ];
    }

    public function theSlug(mixed $entity): string
    {
        return $this->resolver->resolve($entity);
    }
}
