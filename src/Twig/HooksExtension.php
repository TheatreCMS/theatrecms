<?php

namespace TheatreCMS\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the WordPress-style `apply_filters()` helper (see `app/hooks.php`) to Twig
 * templates, so a template can declare its own extension point inline instead of every
 * hookable insertion point needing a bespoke `the_x()` Twig function + resolver pair.
 *
 * Usage in a template, matching plain-PHP usage:
 *
 *   {{ apply_filters('theatrecms/schedule_details_footer', '', upcoming)|raw }}
 *
 * The result is returned as-is (not auto-marked safe HTML), since apply_filters() is
 * generic and not every tag resolves to markup — add `|raw` at the call site when the
 * filtered value is meant to be inserted as HTML, the same way `|raw` is used anywhere
 * else in these templates.
 */
class HooksExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('apply_filters', 'apply_filters'),
        ];
    }
}
