<?php

namespace TheatreCMS\Plugin;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Finds plugins the way themes are found: every subdirectory of the plugins directory whose
 * `composer.json` has type `theatrecms-plugin` and names its plugin class under
 * `extra.theatrecms.plugin`.
 *
 * This only discovers plugins; it does not autoload them. Their classes and dependencies are
 * installed by Composer through `composer.local.json`, which merges every `plugins/*\/composer.json`
 * by bin/composer-local (see documentation/plugins.md). A plugin added without running it is found
 * here, then logged and skipped by PluginManager because its class can't be loaded yet.
 */
class DirectoryPluginDiscovery implements PluginDiscovery
{
    public const PACKAGE_TYPE = 'theatrecms-plugin';

    public function __construct(
        private readonly string $pluginsDir,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function discover(): array
    {
        $descriptors = [];

        foreach (glob(rtrim($this->pluginsDir, '/') . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            $descriptor = $this->describe($path);
            if ($descriptor !== null) {
                $descriptors[$descriptor->package] = $descriptor;
            }
        }

        ksort($descriptors);

        return array_values($descriptors);
    }

    private function describe(string $path): ?PluginDescriptor
    {
        $manifest = $path . '/composer.json';
        if (!is_file($manifest)) {
            $this->skip($path, 'it has no composer.json');
            return null;
        }

        $data = json_decode((string) file_get_contents($manifest), true);
        if (!is_array($data)) {
            $this->skip($path, 'its composer.json is not valid JSON');
            return null;
        }

        if (($data['type'] ?? null) !== self::PACKAGE_TYPE) {
            $this->skip($path, 'its composer.json type is not ' . self::PACKAGE_TYPE);
            return null;
        }

        $class = $data['extra']['theatrecms']['plugin'] ?? null;
        if (!is_string($class) || $class === '') {
            $this->skip($path, 'its composer.json has no extra.theatrecms.plugin class');
            return null;
        }

        $package = is_string($data['name'] ?? null) ? $data['name'] : basename($path);

        return new PluginDescriptor($package, ltrim($class, '\\'), $path);
    }

    private function skip(string $path, string $reason): void
    {
        $this->logger->warning('Skipping plugin directory {path}: {reason}.', [
            'path' => $path,
            'package' => basename($path),
            'reason' => $reason,
        ]);
    }
}
