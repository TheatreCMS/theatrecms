<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\DateResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style `the_date()` corollary for post publish dates.
 */
class DateExtension extends AbstractExtension
{
    public function __construct(private readonly DateResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_date', [$this, 'theDate']),
        ];
    }

    public function theDate(mixed $entity, string $format = 'F j, Y'): string
    {
        return $this->resolver->resolve($entity, $format);
    }
}