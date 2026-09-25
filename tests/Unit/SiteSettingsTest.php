<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TheatreCMS\Settings\SiteSettings;

class SiteSettingsTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/theatrecms-test-config-' . uniqid() . '.yaml';
        file_put_contents($this->configPath, Yaml::dump([
            'site'     => ['name' => 'Old Name'],
            'theme'    => 'default',
            'database' => ['host' => 'db', 'dbname' => 'db'],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->configPath);
    }

    public function testSavePreservesOtherTopLevelKeys(): void
    {
        (new SiteSettings($this->configPath))->save(['name' => 'New Name']);

        $config = Yaml::parseFile($this->configPath);
        $this->assertSame('New Name', $config['site']['name']);
        $this->assertSame('default', $config['theme']);
        $this->assertSame(['host' => 'db', 'dbname' => 'db'], $config['database']);
    }

    public function testSaveThemeUpdatesOnlyTheThemeKey(): void
    {
        (new SiteSettings($this->configPath))->saveTheme('dso');

        $config = Yaml::parseFile($this->configPath);
        $this->assertSame('dso', $config['theme']);
        $this->assertSame('Old Name', $config['site']['name']);
        $this->assertSame(['host' => 'db', 'dbname' => 'db'], $config['database']);
    }
}
