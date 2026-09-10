<?php

namespace TheatreCMS\Tests\Unit\Theme;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Theme\ThemeManager;

class ThemeManagerTest extends TestCase
{
    private string $themesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themesDir = sys_get_temp_dir() . '/theatrecms-test-themes-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->themesDir);
        parent::tearDown();
    }

    private function makeTheme(string $slug, array $themeJson): ThemeManager
    {
        $dir = $this->themesDir . '/' . $slug;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/theme.json', json_encode($themeJson));

        return new ThemeManager($this->themesDir, $slug);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGetColorPaletteNormalizesEntriesAndDefaultsLabel(): void
    {
        $themeManager = $this->makeTheme('normalizes', [
            'settings' => [
                'color' => [
                    'palette' => [
                        ['slug' => 'blue', 'label' => 'Blue', 'color' => '#3b82f6'],
                        ['slug' => 'green', 'color' => '#22c55e'],
                    ],
                ],
            ],
        ]);

        $palette = $themeManager->getColorPalette();

        $this->assertSame(
            [
                ['slug' => 'blue', 'label' => 'Blue', 'color' => '#3b82f6'],
                ['slug' => 'green', 'label' => 'green', 'color' => '#22c55e'],
            ],
            $palette
        );
    }

    public function testGetColorPaletteDropsEntriesMissingSlugOrColor(): void
    {
        $themeManager = $this->makeTheme('drops-incomplete', [
            'settings' => [
                'color' => [
                    'palette' => [
                        ['label' => 'No slug', 'color' => '#3b82f6'],
                        ['slug' => 'no-color'],
                        ['slug' => 'blue', 'color' => '#3b82f6'],
                    ],
                ],
            ],
        ]);

        $palette = $themeManager->getColorPalette();

        $this->assertCount(1, $palette);
        $this->assertSame('blue', $palette[0]['slug']);
    }

    public function testGetColorPaletteDropsEntriesWithUnsafeColorValues(): void
    {
        $themeManager = $this->makeTheme('drops-unsafe', [
            'settings' => [
                'color' => [
                    'palette' => [
                        ['slug' => 'danger', 'color' => 'javascript:alert(1)'],
                        ['slug' => 'danger2', 'color' => 'red; } body { display: none'],
                        ['slug' => 'hex', 'color' => '#a855f7'],
                        ['slug' => 'rgba', 'color' => 'rgb(149, 214, 216, .5)'],
                    ],
                ],
            ],
        ]);

        $palette = $themeManager->getColorPalette();
        $slugs = array_column($palette, 'slug');

        $this->assertSame(['hex', 'rgba'], $slugs);
    }

    public function testGetColorPaletteReturnsEmptyArrayWhenThemeHasNoPalette(): void
    {
        $themeManager = $this->makeTheme('no-palette', [
            'name' => 'No Palette',
        ]);

        $this->assertSame([], $themeManager->getColorPalette());
    }
}
