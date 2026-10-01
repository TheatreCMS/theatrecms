<?php

namespace TheatreCMS\Tests\Unit\Migrations;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Migrations\Migration;
use TheatreCMS\Migrations\MigrationLocator;
use TheatreCMS\Plugin\PluginManager;

class MigrationLocatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/theatrecms-locator-test-' . bin2hex(random_bytes(4));
        foreach (['core/legacy', 'alpha', 'beta'] as $directory) {
            mkdir($this->root . '/' . $directory, 0777, true);
        }
        foreach (['core/20261001_b.sql', 'core/legacy/20250101_old.sql', 'core/notes.txt', 'alpha/20261001_b.sql', 'alpha/20260901_a.sql', 'beta/20261001_b.sql'] as $file) {
            touch($this->root . '/' . $file);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testOrdersByFilenameThenCoreThenPackage(): void
    {
        $plugins = $this->createStub(PluginManager::class);
        $plugins->method('migrationPaths')->willReturn([
            'vendor/beta' => [$this->root . '/beta'],
            'vendor/alpha' => [$this->root . '/alpha', $this->root . '/missing'],
        ]);

        $labels = array_map(
            static fn(Migration $migration): string => $migration->label(),
            (new MigrationLocator($this->root . '/core', $plugins))->all(),
        );

        $this->assertSame([
            'vendor/alpha/20260901_a.sql',
            'core/20261001_b.sql',
            'vendor/alpha/20261001_b.sql',
            'vendor/beta/20261001_b.sql',
        ], $labels);
    }
}
