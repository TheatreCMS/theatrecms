<?php

namespace TheatreCMS\Plugin;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Finds plugins among the installed Composer packages: every package of type `theatrecms-plugin`
 * whose `composer.json` declares its plugin class under `extra.theatrecms.plugin`. Packages are
 * autoloaded by Composer, so no files are required here.
 */
class ComposerPluginDiscovery implements PluginDiscovery
{
    public const PACKAGE_TYPE = 'theatrecms-plugin';

    /**
     * @var \Closure(): array<string, string>
     */
    private \Closure $packages;

    /**
     * @param (callable(): array<string, string>)|null $packages Package name => install path;
     *        defaults to the plugins recorded by Composer's InstalledVersions. Injectable for tests.
     */
    public function __construct(?callable $packages = null, private readonly LoggerInterface $logger = new NullLogger())
    {
        $this->packages = $packages !== null ? \Closure::fromCallable($packages) : self::installedPackages(...);
    }

    public function discover(): array
    {
        $descriptors = [];

        foreach (($this->packages)() as $package => $path) {
            $class = $this->pluginClass($package, $path);
            if ($class !== null) {
                $descriptors[$package] = new PluginDescriptor($package, $class, $path);
            }
        }

        ksort($descriptors);

        return array_values($descriptors);
    }

    /**
     * @return array<string, string>
     */
    private static function installedPackages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getInstalledPackagesByType(self::PACKAGE_TYPE) as $package) {
            $path = InstalledVersions::getInstallPath($package);
            if ($path !== null) {
                $packages[$package] = $path;
            }
        }

        return $packages;
    }

    private function pluginClass(string $package, string $path): ?string
    {
        $manifest = rtrim($path, '/') . '/composer.json';
        $data = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
        $class = is_array($data) ? ($data['extra']['theatrecms']['plugin'] ?? null) : null;

        if (!is_string($class) || $class === '') {
            $this->logger->warning('Plugin package {package} has no extra.theatrecms.plugin class; skipping.', [
                'package' => $package,
            ]);
            return null;
        }

        return ltrim($class, '\\');
    }
}
