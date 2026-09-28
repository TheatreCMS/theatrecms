<?php

namespace TheatreCMS\Twig;

use TheatreCMS\Admin\AdminMenuRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the admin sidebar to `templates/layouts/admin.html.twig` as `admin_menu()`.
 * Capability filtering stays in the template, via `can()`.
 */
class AdminMenuExtension extends AbstractExtension
{
    public function __construct(private readonly AdminMenuRegistry $registry)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_menu', $this->registry->groups(...)),
        ];
    }
}
