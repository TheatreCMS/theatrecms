<?php

namespace TheatreCMS\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Plugin\DirectoryPluginDiscovery;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExamplePlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\BetaPlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\RecordingLogger;

/**
 * @coversDefaultClass \TheatreCMS\Plugin\DirectoryPluginDiscovery
 */
class DirectoryPluginDiscoveryTest extends TestCase
{
    private const PLUGINS_DIR = __DIR__ . '/../../Fixtures/PluginsDir';

    public function testFindsPluginFoldersSortedByPackageName(): void
    {
        $descriptors = (new DirectoryPluginDiscovery(self::PLUGINS_DIR))->discover();

        $this->assertSame(
            ['acme/beta-plugin', 'theatrecms/example-plugin'],
            array_map(static fn($d) => $d->package, $descriptors),
        );
        $this->assertSame(BetaPlugin::class, $descriptors[0]->class, 'a leading backslash is stripped');
        $this->assertSame(ExamplePlugin::class, $descriptors[1]->class);
        $this->assertSame(self::PLUGINS_DIR . '/example', $descriptors[1]->path);
    }

    public function testSkipsAndLogsFoldersThatAreNotPlugins(): void
    {
        $logger = new RecordingLogger();

        (new DirectoryPluginDiscovery(self::PLUGINS_DIR, $logger))->discover();

        $skipped = $logger->failedPackages();
        sort($skipped);
        $this->assertSame(['no-class', 'no-manifest', 'wrong-type'], $skipped);
    }

    public function testMissingPluginsDirectoryFindsNothing(): void
    {
        $this->assertSame([], (new DirectoryPluginDiscovery('/nonexistent/plugins'))->discover());
    }

    public function testFallsBackToFolderNameWhenManifestHasNoName(): void
    {
        $dir = sys_get_temp_dir() . '/theatrecms-plugins-' . bin2hex(random_bytes(4));
        mkdir($dir . '/unnamed', 0777, true);
        file_put_contents($dir . '/unnamed/composer.json', json_encode([
            'type' => 'theatrecms-plugin',
            'extra' => ['theatrecms' => ['plugin' => 'Acme\\Unnamed\\Plugin']],
        ]));

        try {
            $descriptors = (new DirectoryPluginDiscovery($dir))->discover();
            $this->assertSame('unnamed', $descriptors[0]->package);
        } finally {
            unlink($dir . '/unnamed/composer.json');
            rmdir($dir . '/unnamed');
            rmdir($dir);
        }
    }
}
