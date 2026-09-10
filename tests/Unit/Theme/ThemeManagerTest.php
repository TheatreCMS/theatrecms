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
                        ['name' => 'blue', 'label' => 'Blue', 'color' => '#3b82f6'],
                        ['name' => 'green', 'color' => '#22c55e'],
                    ],
                ],
            ],
        ]);

        $palette = $themeManager->getColorPalette();

        $this->assertSame(
            [
                ['name' => 'blue', 'label' => 'Blue', 'color' => '#3b82f6'],
                ['name' => 'green', 'label' => 'green', 'color' => '#22c55e'],
            ],
            $palette
        );
    }

    public function testGetColorPaletteDropsEntriesMissingNameOrColor(): void
    {
        $themeManager = $this->makeTheme('drops-incomplete', [
            'settings' => [
                'color' => [
                    'palette' => [
                        ['label' => 'No name', 'color' => '#3b82f6'],
                        ['name' => 'no-color'],
                        ['name' => 'blue', 'color' => '#3b82f6'],
                    ],
                ],
            ],
        ]);

        $palette = $themeManager->getColorPalette();

        $this->assertCount(1, $palette);
        $this->assertSame('blue', $palette[0]['name']);
    }

    public function testGetColorPaletteDropsEntriesWithUnsafeColorValues(): void
    {
        $themeManager = $this->makeTheme('drops-unsafe', [
            'settings' => [
                'color' => [
                    'palette' => [
                        ['name' => 'danger', 'color' => 'javascript:alert(1)'],
                        ['name' => 'danger2', 'color' => 'red; } body { display: none'],
                        ['name' => 'hex', 'color' => '#a855f7'],
                        ['name' => 'rgba', 'color' => 'rgb(149, 214, 216, .5)'],
                    ],
                ],
            ],
        ]);

        $palette = $themeManager->getColorPalette();
        $names = array_column($palette, 'name');

        $this->assertSame(['hex', 'rgba'], $names);
    }

    public function testGetColorPaletteReturnsEmptyArrayWhenThemeHasNoPalette(): void
    {
        $themeManager = $this->makeTheme('no-palette', [
            'name' => 'No Palette',
        ]);

        $this->assertSame([], $themeManager->getColorPalette());
    }
}
