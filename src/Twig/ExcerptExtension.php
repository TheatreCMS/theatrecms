<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\ExcerptResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style `the_excerpt()` corollary: a single function themes can call on any
 * content entity to get its excerpt without needing to know which model type it is.
 *
 * The resolved excerpt is passed through the `theatrecms/the_excerpt` filter, mirroring
 * the rest of the theme helper stack exposed by this project.
 */
class ExcerptExtension extends AbstractExtension
{
    public function __construct(private readonly ExcerptResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_excerpt', [$this, 'theExcerpt']),
        ];
    }

    public function theExcerpt(mixed $entity): string
    {
        return $this->resolver->resolve($entity);
    }
}
