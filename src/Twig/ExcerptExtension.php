<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\ExcerptResolver;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * WordPress-style `the_excerpt()` corollary: a single function themes can call on any
 * content entity to get its excerpt without needing to know which model type it is.
 *
 * Excerpts are plain text (no rich-text/EditorJS input), but authors may still enter
 * line breaks, so — mirroring `Twig\AddressExtension` — the resolved text is escaped
 * and rendered as HTML with a `<br>` between lines rather than collapsing them.
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
            new TwigFunction('the_excerpt', [$this, 'theExcerpt'], ['is_safe' => ['html']]),
        ];
    }

    public function theExcerpt(mixed $entity): Markup
    {
        $excerpt = $this->resolver->resolve($entity);

        return new Markup(nl2br(htmlspecialchars($excerpt, ENT_QUOTES), false), 'UTF-8');
    }
}
