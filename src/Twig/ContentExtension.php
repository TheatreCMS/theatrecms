<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\ContentResolver;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * WordPress-style `the_content()` corollary: a single function themes can call on any
 * content entity, without needing to know that its content is Editor.js JSON, already-
 * sanitized HTML, or anything else.
 *
 * The resolved HTML is passed through the `theatrecms/the_content` filter, mirroring
 * WordPress' own filterable `the_content` — see documentation/Theme/hooks.md.
 */
class ContentExtension extends AbstractExtension
{
    public function __construct(private readonly ContentResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_content', [$this, 'theContent'], ['is_safe' => ['html']]),
        ];
    }

    public function theContent(mixed $entity): Markup
    {
        return new Markup($this->resolver->resolve($entity), 'UTF-8');
    }
}
