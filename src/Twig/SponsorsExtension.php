<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\SponsorsResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes `the_sponsors()`: a single function themes can call on any content entity to get its
 * sponsor names as a comma-separated list without needing to know which model type it is.
 *
 * The resolved list is passed through the `theatrecms/the_sponsors` filter, mirroring the rest
 * of the theme helper stack exposed by this project.
 */
class SponsorsExtension extends AbstractExtension
{
    public function __construct(private readonly SponsorsResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_sponsors', [$this, 'theSponsors']),
        ];
    }

    public function theSponsors(mixed $entity): string
    {
        return $this->resolver->resolve($entity);
    }
}
