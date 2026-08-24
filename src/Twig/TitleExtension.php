<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\TitleResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style `the_title()` corollary: a single function themes can call on any
 * content entity, without needing to know that a Production's title lives on `name`,
 * a Season's on `label`, a Person's is composed from first/last name, etc.
 *
 * The resolved title is passed through the `theatrecms/the_title` filter, mirroring
 * WordPress' own filterable `the_title` — see documentation/Theme/hooks.md.
 */
class TitleExtension extends AbstractExtension
{
    public function __construct(private readonly TitleResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_title', [$this, 'theTitle']),
        ];
    }

    public function theTitle(mixed $entity): string
    {
        return $this->resolver->resolve($entity);
    }
}
