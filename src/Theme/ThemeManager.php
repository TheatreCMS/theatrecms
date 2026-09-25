<?php

namespace TheatreCMS\Theme;

use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;

class ThemeManager
{
    private string $themesDir;
    private string $activeTheme;
    private array $themeData = [];
    private bool $functionsLoaded = false;

    public function __construct(string $themesDir, string $activeTheme = 'default')
    {
        $this->themesDir  = rtrim($themesDir, '/');
        $this->activeTheme = $activeTheme;
    }

    public function getActiveTheme(): string
    {
        return $this->activeTheme;
    }

    /**
     * List every installed theme: each subdirectory of the themes dir that has a
     * `theme.json`. The directory name is the theme's identifier (what the
     * `theme` config key holds); `theme.json` supplies display metadata only.
     *
     * @return array<string, array{slug: string, name: string, description: string,
     *     version: string, author: string, screenshot: ?string}>
     */
    public function getAvailableThemes(): array
    {
        $themes = [];

        foreach (glob($this->themesDir . '/*/theme.json') ?: [] as $jsonFile) {
            $dir = dirname($jsonFile);
            $slug = basename($dir);
            $metadata = json_decode((string) file_get_contents($jsonFile), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $screenshot = null;
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                if (is_file($dir . '/screenshot.' . $ext)) {
                    $screenshot = 'screenshot.' . $ext;
                    break;
                }
            }

            $themes[$slug] = [
                'slug'        => $slug,
                'name'        => (string) ($metadata['name'] ?? $slug),
                'description' => (string) ($metadata['description'] ?? ''),
                'version'     => (string) ($metadata['version'] ?? ''),
                'author'      => (string) ($metadata['author'] ?? ''),
                'screenshot'  => $screenshot,
            ];
        }

        ksort($themes);

        return $themes;
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
     * Read the active theme's declared color palette from `theme.json`
     * (`settings.color.palette`, mirroring WordPress's theme.json convention).
     *
     * Each entry is normalized to ['name' => ..., 'label' => ..., 'color' => ...].
     * Entries missing a name, or whose color doesn't match a safe CSS color
     * pattern (hex / rgb(a) / hsl(a)), are dropped — this is the one place
     * both server-rendered HTML and the admin editor end up trusting a
     * theme-declared value for, so it must not pass through unchecked.
     *
     * @return array<int, array{name: string, label: string, color: string}>
     */
    public function getColorPalette(): array
    {
        $palette = $this->getMetadata()['settings']['color']['palette'] ?? [];
        if (!is_array($palette)) {
            return [];
        }

        $normalized = [];
        foreach ($palette as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $color = trim((string) ($entry['color'] ?? ''));

            if ($name === '' || !self::isValidCssColor($color)) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));

            $normalized[] = [
                'name' => $name,
                'label' => $label !== '' ? $label : $name,
                'color' => $color,
            ];
        }

        return $normalized;
    }

    public static function isValidCssColor(string $color): bool
    {
        $colorPattern = '/^(#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})'
            . '|(rgb|hsl)a?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+\s*)?\))$/';

        return preg_match($colorPattern, trim($color)) === 1;
    }

    /**
     * Load the theme's functions.php (equivalent to WP's functions.php)
     */
    public function loadFunctions(): void
    {
        if ($this->functionsLoaded) {
            return;
        }

        $functions = $this->getThemeDir() . '/functions.php';
        if (file_exists($functions)) {
            require_once $functions;
        }

        $this->functionsLoaded = true;
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
