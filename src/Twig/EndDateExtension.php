<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\EndDateResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style corollary for resolving the end date of seasons and productions,
 * mirroring `Twig\DateExtension`'s `the_date()` function.
 */
class EndDateExtension extends AbstractExtension
{
    public function __construct(private readonly EndDateResolver $resolver) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_end_date', [$this, 'theEndDate']),
        ];
    }

    public function theEndDate(mixed $entity, string $format = 'F j, Y'): string
    {
        $content = '';
        $endDate = $this->resolver->resolve($entity);

        if ($endDate) {
            $content = apply_filters('the_end_date', $endDate->format($format), $endDate);
        }

        return $content;
    }
}
