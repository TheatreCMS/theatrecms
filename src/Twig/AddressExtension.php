<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\AddressResolver;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * `the_address()`: a single function themes can call on a Venue to get its mailing
 * address in the typical United States two-line format (street address, then
 * "City, State ZIP"), rendered as HTML with a `<br>` between the two lines.
 */
class AddressExtension extends AbstractExtension
{
    public function __construct(private readonly AddressResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_address', [$this, 'theAddress'], ['is_safe' => ['html']]),
        ];
    }

    public function theAddress(mixed $entity): Markup
    {
        $address = $this->resolver->resolve($entity);

        return new Markup(nl2br(htmlspecialchars($address, ENT_QUOTES), false), 'UTF-8');
    }
}
