<?php

namespace TheatreCMS\Theme;

use Slim\Views\Twig;

/**
 * Resolves a template from a list of candidates, returning the first one that exists. This is used
 * to implement the template hierarchy, where more specific templates are checked before more generic ones.
 *
 * For example, when rendering a single production, the candidates might be:
 * - productions/single-{production-slug}.html.twig
 * - productions/single.html.twig
 * - single.html.twig
 * - index.html.twig
 */
class TemplateResolver
{
    public function resolve(Twig $twig, string ...$candidates): string
    {
        $loader = $twig->getLoader();

        foreach ($candidates as $template) {
            if ($loader->exists($template)) {
                return $template;
            }
        }

        return end($candidates); // fallback to last (most generic)
    }
}
