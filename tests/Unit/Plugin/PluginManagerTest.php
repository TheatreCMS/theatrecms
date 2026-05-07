<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheatreCMS\Plugin\PluginManager;

class PluginManagerTest extends TestCase
{
    private string $tmpDir;
    private string $pluginsDir;
    private string $configFile;
    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir    = sys_get_temp_dir() . '/theatrecms_plugin_test_' . uniqid();
        $this->pluginsDir = $this->tmpDir . '/plugins';
        $this->configFile = $this->tmpDir . '/plugins.json';

        mkdir($this->pluginsDir, 0755, true);

        $this->manager = new PluginManager($this->pluginsDir, $this->configFile);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // getActiveSlugs()
    // -------------------------------------------------------------------------

    public function testGetActiveSlugsReturnEmptyArrayWhenConfigFileMissing(): void
    {
        self::assertSame([], $this->manager->getActiveSlugs());
    }

    public function testGetActiveSlugsReturnEmptyArrayForEmptyActiveList(): void
    {
        $this->writeConfig(['active' => []]);
        self::assertSame([], $this->manager->getActiveSlugs());
    }

    public function testGetActiveSlugsReturnsSlugs(): void
    {
        $this->writeConfig(['active' => ['foo', 'bar']]);
        self::assertSame(['foo', 'bar'], $this->manager->getActiveSlugs());
    }

    public function testGetActiveSlugsReturnEmptyArrayForInvalidJson(): void
    {
        file_put_contents($this->configFile, 'not-json{{{');
        self::assertSame([], $this->manager->getActiveSlugs());
    }

    public function testGetActiveSlugsReturnEmptyArrayWhenActiveKeyMissing(): void
    {
        $this->writeConfig(['other' => 'value']);
        self::assertSame([], $this->manager->getActiveSlugs());
    }

    public function testGetActiveSlugsReturnEmptyArrayWhenActiveIsNotAnArray(): void
    {
        $this->writeConfig(['active' => 'single-string']);
        self::assertSame([], $this->manager->getActiveSlugs());
    }

    // -------------------------------------------------------------------------
    // activate()
    // -------------------------------------------------------------------------

    public function testActivateAddsSlugToConfig(): void
    {
        $this->createDummyPlugin('my-plugin');

        $this->manager->activate('my-plugin');

        self::assertSame(['my-plugin'], $this->manager->getActiveSlugs());
    }

    public function testActivateIsIdempotent(): void
    {
        $this->createDummyPlugin('my-plugin');
        $this->writeConfig(['active' => ['my-plugin']]);

        $this->manager->activate('my-plugin');

        self::assertSame(['my-plugin'], $this->manager->getActiveSlugs());
    }

    public function testActivateThrowsForMissingPluginDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->manager->activate('nonexistent');
    }

    public function testActivateThrowsForInvalidSlug(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid characters/');

        $this->manager->activate('../evil');
    }

    public function testActivateCreatesConfigDirectoryIfMissing(): void
    {
        $nestedConfig = $this->tmpDir . '/nested/dir/plugins.json';
        $manager = new PluginManager($this->pluginsDir, $nestedConfig);
        $this->createDummyPlugin('my-plugin');

        $manager->activate('my-plugin');

        self::assertFileExists($nestedConfig);
    }

    // -------------------------------------------------------------------------
    // deactivate()
    // -------------------------------------------------------------------------

    public function testDeactivateRemovesSlugFromConfig(): void
    {
        $this->writeConfig(['active' => ['foo', 'bar']]);

        $this->manager->deactivate('foo');

        self::assertSame(['bar'], $this->manager->getActiveSlugs());
    }

    public function testDeactivateIsIdempotentForAbsentSlug(): void
    {
        $this->writeConfig(['active' => ['bar']]);

        $this->manager->deactivate('nonexistent');

        self::assertSame(['bar'], $this->manager->getActiveSlugs());
    }

    // -------------------------------------------------------------------------
    // Missing-plugin graceful handling
    // -------------------------------------------------------------------------

    public function testRegisterAllSkipsMissingPlugin(): void
    {
        $this->writeConfig(['active' => ['missing-plugin']]);

        $this->expectWarning();
        $this->expectWarningMessageMatches('/not found/');

        // Should not throw; the warning is emitted and the plugin is skipped.
        $this->manager->registerAll($this->createMock(\DI\Container::class));
    }

    // -------------------------------------------------------------------------
    // discoverAll()
    // -------------------------------------------------------------------------

    public function testDiscoverAllReturnsInstalledPlugins(): void
    {
        $this->createDummyPlugin('alpha');
        $this->createDummyPlugin('beta');

        $discovered = $this->manager->discoverAll();

        self::assertArrayHasKey('alpha', $discovered);
        self::assertArrayHasKey('beta', $discovered);
    }

    public function testDiscoverAllReturnsEmptyArrayWhenNoneInstalled(): void
    {
        self::assertSame([], $this->manager->discoverAll());
    }

    // -------------------------------------------------------------------------
    // getMetadata()
    // -------------------------------------------------------------------------

    public function testGetMetadataReturnsEmptyArrayWhenJsonFileMissing(): void
    {
        self::assertSame([], $this->manager->getMetadata('no-such-plugin'));
    }

    public function testGetMetadataReturnsParsedJson(): void
    {
        $this->createDummyPlugin('my-plugin', ['name' => 'My Plugin', 'version' => '1.0.0']);

        $meta = $this->manager->getMetadata('my-plugin');

        self::assertSame('My Plugin', $meta['name']);
        self::assertSame('1.0.0', $meta['version']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeConfig(array $data): void
    {
        file_put_contents($this->configFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function createDummyPlugin(string $slug, array $meta = []): void
    {
        $dir = $this->pluginsDir . '/' . $slug;
        mkdir($dir, 0755, true);
        // Minimal Plugin.php so file_exists() passes for activate()
        file_put_contents($dir . '/Plugin.php', '<?php // dummy');
        if ($meta !== []) {
            file_put_contents($dir . '/plugin.json', json_encode($meta));
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
