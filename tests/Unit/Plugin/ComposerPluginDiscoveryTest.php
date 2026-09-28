<?php

namespace TheatreCMS\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Plugin\ComposerPluginDiscovery;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExamplePlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\RecordingLogger;

/**
 * @coversDefaultClass \TheatreCMS\Plugin\ComposerPluginDiscovery
 */
class ComposerPluginDiscoveryTest extends TestCase
{
    private const EXAMPLE_PATH = __DIR__ . '/../../Fixtures/Plugins/Example';

    public function testReadsPluginClassFromComposerExtra(): void
    {
        $discovery = new ComposerPluginDiscovery(static fn(): array => [
            'theatrecms/example-plugin' => self::EXAMPLE_PATH,
        ]);

        $descriptors = $discovery->discover();

        $this->assertCount(1, $descriptors);
        $this->assertSame('theatrecms/example-plugin', $descriptors[0]->package);
        $this->assertSame(ExamplePlugin::class, $descriptors[0]->class);
        $this->assertSame(self::EXAMPLE_PATH, $descriptors[0]->path);
    }

    public function testSkipsAndLogsPackagesWithoutAPluginClass(): void
    {
        $logger = new RecordingLogger();
        $discovery = new ComposerPluginDiscovery(static fn(): array => [
            'vendor/no-manifest' => '/nonexistent/path',
            'theatrecms/example-plugin' => self::EXAMPLE_PATH,
        ], $logger);

        $descriptors = $discovery->discover();

        $this->assertSame(['theatrecms/example-plugin'], array_map(static fn($d) => $d->package, $descriptors));
        $this->assertSame(['vendor/no-manifest'], $logger->failedPackages());
    }

    public function testSortsByPackageName(): void
    {
        $discovery = new ComposerPluginDiscovery(static fn(): array => [
            'zeta/plugin' => self::EXAMPLE_PATH,
            'alpha/plugin' => self::EXAMPLE_PATH,
        ]);

        $packages = array_map(static fn($d) => $d->package, $discovery->discover());

        $this->assertSame(['alpha/plugin', 'zeta/plugin'], $packages);
    }

    public function testDefaultSourceReadsInstalledComposerPackages(): void
    {
        // Core itself has no theatrecms-plugin packages installed.
        $this->assertSame([], (new ComposerPluginDiscovery())->discover());
    }
}
