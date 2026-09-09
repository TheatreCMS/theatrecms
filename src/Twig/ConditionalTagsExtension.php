<?php

namespace TheatreCMS\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the WordPress-style conditional tags (see `app/template-tags.php`) to Twig
 * templates, e.g. `{% if is_single() %}`.
 */
class ConditionalTagsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_single', 'is_single'),
            new TwigFunction('is_item', 'is_item'),
            new TwigFunction('is_item_id', 'is_item_id'),
            new TwigFunction('is_archive', 'is_archive'),
            new TwigFunction('is_singular', 'is_singular'),
        ];
    }
}
