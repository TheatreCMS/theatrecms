<?php

namespace TheatreCMS\Theme;

use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;

class ThemeManager
{
    private string $themesDir;
    private string $activeTheme;
    private array $themeData = [];

    public function __construct(string $themesDir, string $activeTheme = 'default')
    {
        $this->themesDir  = rtrim($themesDir, '/');
        $this->activeTheme = $activeTheme;
    }

    public function getThemeDir(): string
    {
        return $this->themesDir . '/' . $this->activeTheme;
    }

    public function getTemplatesDir(): string
    {
        return $this->getThemeDir() . '/templates';
    }

    public function getMetadata(): array
    {
        if (empty($this->themeData)) {
            $json = $this->getThemeDir() . '/theme.json';
            $this->themeData = file_exists($json)
                ? json_decode(file_get_contents($json), true)
                : [];
        }
        return $this->themeData;
    }

    /**
     * Load the theme's functions.php (equivalent to WP's functions.php)
     */
    public function loadFunctions(): void
    {
        $functions = $this->getThemeDir() . '/functions.php';
        if (file_exists($functions)) {
            require_once $functions;
        }
    }

    /**
     * Configure Twig to check theme templates first, fall back to core templates.
     */
    public function configureTwig(Twig $twig, string $coreTemplatesDir): void
    {
        /** @var FilesystemLoader $loader */
        $loader = $twig->getLoader();

        // Theme templates take priority (prepend)
        if (is_dir($this->getTemplatesDir())) {
            $loader->prependPath($this->getTemplatesDir());
        }

        // Core templates as fallback namespace
        $loader->addPath($coreTemplatesDir, 'core');
    }
}