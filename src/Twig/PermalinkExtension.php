<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Theme\PermalinkResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * WordPress-style `the_permalink()` corollary: a single function themes can call on any
 * content entity to get its canonical relative URL, and `archive_url()` for a content
 * type's listing page — without hardcoding a URL prefix that a site may have rewritten
 * (see `ContentTypeRegistry`).
 */
class PermalinkExtension extends AbstractExtension
{
    public function __construct(private readonly PermalinkResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('the_permalink', [$this, 'thePermalink']),
            new TwigFunction('archive_url', [$this, 'archiveUrl']),
        ];
    }

    public function thePermalink(mixed $entity): string
    {
        return $this->resolver->resolve($entity);
    }

    public function archiveUrl(string $type): string
    {
        return $this->resolver->archiveUrl($type);
    }
}
