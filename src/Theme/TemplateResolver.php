<?php

namespace TheatreCMS\Theme;

use Slim\Views\Twig;

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
