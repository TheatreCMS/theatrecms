<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Twig\AssetExtension;

final class AssetExtensionTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/asset-extension-' . bin2hex(random_bytes(4));
        mkdir($this->publicDir . '/assets/js/vendor', 0777, true);
        mkdir($this->publicDir . '/assets/css', 0777, true);

        $this->writeFile('/assets/js/menu.js', 1700000000);
        $this->writeFile('/assets/js/editorjs-config.js', 1700000100);
        $this->writeFile('/assets/js/vendor/lib.js', 1700000200);
        $this->writeFile('/assets/css/site.css', 1700000300);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->publicDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->publicDir);
    }

    public function testAssetAppendsTheFileModificationTime(): void
    {
        $extension = new AssetExtension($this->publicDir);

        $this->assertSame('/assets/css/site.css?v=' . dechex(1700000300), $extension->asset('/assets/css/site.css'));
    }

    public function testAssetChangesWhenTheFileChanges(): void
    {
        $before = (new AssetExtension($this->publicDir))->asset('/assets/js/menu.js');
        $this->writeFile('/assets/js/menu.js', 1700009999);
        $after = (new AssetExtension($this->publicDir))->asset('/assets/js/menu.js');

        $this->assertNotSame($before, $after);
        $this->assertSame('/assets/js/menu.js?v=' . dechex(1700009999), $after);
    }

    public function testAssetKeepsAnExistingQueryString(): void
    {
        $extension = new AssetExtension($this->publicDir);

        $this->assertSame(
            '/assets/js/menu.js?x=1&v=' . dechex(1700000000),
            $extension->asset('/assets/js/menu.js?x=1')
        );
    }

    public function testAssetLeavesNonLocalPathsUnchanged(): void
    {
        $extension = new AssetExtension($this->publicDir);

        $this->assertSame('/assets/js/missing.js', $extension->asset('/assets/js/missing.js'));
        $this->assertSame('https://cdn.example.com/x.js', $extension->asset('https://cdn.example.com/x.js'));
        $this->assertSame('//cdn.example.com/x.js', $extension->asset('//cdn.example.com/x.js'));
        $this->assertSame('/assets/../secret.txt', $extension->asset('/assets/../secret.txt'));
        $this->assertSame('/assets/js', $extension->asset('/assets/js'));
    }

    public function testImportMapVersionsTopLevelModulesAndExtraEntries(): void
    {
        $html = (new AssetExtension($this->publicDir))->importMap([
            'lib' => '/assets/js/vendor/lib.js',
            'lib/' => '/assets/js/vendor/',
        ]);

        $this->assertStringStartsWith('<script type="importmap">', $html);
        $this->assertStringEndsWith('</script>', $html);

        $json = substr($html, strlen('<script type="importmap">'), -strlen('</script>'));
        $imports = json_decode($json, true, 512, JSON_THROW_ON_ERROR)['imports'];

        $this->assertSame([
            '/assets/js/editorjs-config.js' => '/assets/js/editorjs-config.js?v=' . dechex(1700000100),
            '/assets/js/menu.js' => '/assets/js/menu.js?v=' . dechex(1700000000),
            'lib' => '/assets/js/vendor/lib.js?v=' . dechex(1700000200),
            'lib/' => '/assets/js/vendor/',
        ], $imports);
    }

    private function writeFile(string $path, int $mtime): void
    {
        file_put_contents($this->publicDir . $path, '/* test */');
        touch($this->publicDir . $path, $mtime);
        clearstatcache(true, $this->publicDir . $path);
    }
}
