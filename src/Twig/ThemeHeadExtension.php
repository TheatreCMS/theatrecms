<?php
namespace TheatreCMS\Twig;

use Twig\TwigFunction;

class ThemeHeadExtension extends \Twig\Extension\AbstractExtension
{

    /**
     * This method returns an array of TwigFunction instances that define
     * the custom functions provided by this extension.
     */
    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('theme_head', [$this, 'renderThemeHead'], ['is_safe' => ['html']]),
        ];
    }

    public function renderThemeHead(): string
    {
        $headContent = '';

        // Apply filters to allow themes and plugins to modify the head content
        $headContent = \TheatreCMS\Theme\HookManager::getInstance()->applyFilters('theme_head', $headContent);

        return $headContent;
    }
}