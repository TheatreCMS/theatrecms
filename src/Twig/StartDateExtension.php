<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\StartDateResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style corollary for resolving the start date of seasons and productions,
 * mirroring `Twig\DateExtension`'s `the_date()` function.
 */
class StartDateExtension extends AbstractExtension
{
    public function __construct(private readonly StartDateResolver $resolver) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_start_date', [$this, 'theStartDate']),
        ];
    }

    public function theStartDate(mixed $entity, string $format = 'm-d-Y'): string
    {
        $content   = '';
        $startDate = $this->resolver->resolve($entity);

        if ($startDate) {
            $content = apply_filters('the_start_date', $startDate->format($format), $startDate);
        }

        return $content;
    }
}
