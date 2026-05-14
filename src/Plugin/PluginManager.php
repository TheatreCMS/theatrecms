<?php

/**
 *
 * Copyright (C) 2026  TheatreCMS Team (https://theatrecms.dev)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

namespace TheatreCMS\Plugin;

use DI\Container;
use RuntimeException;
use Slim\App;

class PluginManager
{
    /** @var PluginInterface[] Indexed by slug */
    private array $loaded = [];

    public function __construct(
        private readonly string $pluginsDir,
        private readonly string $configFile,
    ) {
    }

    /**
     * Returns the active plugin slugs from the JSON config file.
     *
     * @return string[]
     */
    public function getActiveSlugs(): array
    {
        if (!file_exists($this->configFile)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->configFile), true);

        if (!is_array($data) || !isset($data['active']) || !is_array($data['active'])) {
            return [];
        }

        return array_values(array_filter($data['active'], 'is_string'));
    }

    /**
     * Phase 1 — instantiate every active plugin and call register().
     *
     * Missing, invalid, or throwing plugins emit E_USER_WARNING and are skipped
     * so that one broken plugin does not prevent the application from booting.
     */
    public function registerAll(Container $container): void
    {
        foreach ($this->getActiveSlugs() as $slug) {
            $plugin = $this->loadPlugin($slug);
            if ($plugin === null) {
                continue;
            }
            try {
                $plugin->register($container);
                $this->loaded[$slug] = $plugin;
            } catch (\Throwable $e) {
                trigger_error(
                    "Plugin '$slug' threw during register(): " . $e->getMessage(),
                    E_USER_WARNING
                );
            }
        }
    }

    /**
     * Phase 2 — call boot() on every already-registered plugin.
     *
     * Must be called after registerAll() and after the Slim App is created.
     */
    public function bootAll(App $app): void
    {
        foreach ($this->loaded as $slug => $plugin) {
            try {
                $plugin->boot($app);
            } catch (\Throwable $e) {
                trigger_error(
                    "Plugin '$slug' threw during boot(): " . $e->getMessage(),
                    E_USER_WARNING
                );
            }
        }
    }

    /**
     * Adds a slug to the active list and persists the config file.
     *
     * @throws RuntimeException if the plugin's Plugin.php is missing or the slug is invalid.
     */
    public function activate(string $slug): void
    {
        if (!$this->isValidSlug($slug)) {
            throw new RuntimeException("Plugin slug '$slug' contains invalid characters (allowed: a-z, 0-9, -).");
        }

        $pluginFile = $this->pluginsDir . '/' . $slug . '/Plugin.php';
        if (!file_exists($pluginFile)) {
            throw new RuntimeException("Plugin '$slug' not found at $pluginFile");
        }

        $active = $this->getActiveSlugs();
        if (!in_array($slug, $active, true)) {
            $active[] = $slug;
            $this->writeConfig($active);
        }
    }

    /**
     * Removes a slug from the active list and persists the config file.
     */
    public function deactivate(string $slug): void
    {
        $active = array_values(
            array_filter($this->getActiveSlugs(), fn(string $s) => $s !== $slug)
        );
        $this->writeConfig($active);
    }

    /**
     * Returns metadata from plugin.json for a given slug, or [] if absent or invalid.
     *
     * @return array<string, string>
     */
    public function getMetadata(string $slug): array
    {
        $metaFile = $this->pluginsDir . '/' . $slug . '/plugin.json';
        if (!file_exists($metaFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($metaFile), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Scans the plugins directory and returns metadata for every installed plugin,
     * regardless of active state. Keyed by slug.
     *
     * @return array<string, array<string, string>>
     */
    public function discoverAll(): array
    {
        $plugins = [];
        $files = glob($this->pluginsDir . '/*/Plugin.php') ?: [];

        foreach ($files as $pluginFile) {
            $slug = basename(dirname($pluginFile));
            $plugins[$slug] = $this->getMetadata($slug);
        }

        return $plugins;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function loadPlugin(string $slug): ?PluginInterface
    {
        if (!$this->isValidSlug($slug)) {
            trigger_error(
                "Plugin slug '$slug' contains invalid characters; skipping.",
                E_USER_WARNING
            );
            return null;
        }

        $pluginFile = $this->pluginsDir . '/' . $slug . '/Plugin.php';

        if (!file_exists($pluginFile)) {
            trigger_error(
                "Plugin '$slug' listed in plugins.json but '$pluginFile' was not found.",
                E_USER_WARNING
            );
            return null;
        }

        // Guard against path traversal: ensure the resolved path stays inside pluginsDir.
        $realPluginFile = realpath($pluginFile);
        $realPluginsDir = realpath($this->pluginsDir);
        if (
            $realPluginFile === false ||
            $realPluginsDir === false ||
            !str_starts_with($realPluginFile, $realPluginsDir . DIRECTORY_SEPARATOR)
        ) {
            trigger_error(
                "Plugin '$slug': resolved path is outside the plugins directory; skipping.",
                E_USER_WARNING
            );
            return null;
        }

        require_once $realPluginFile;

        $class = $this->resolveClass($slug);

        if (!class_exists($class)) {
            trigger_error(
                "Plugin class '$class' not found after requiring '$realPluginFile'.",
                E_USER_WARNING
            );
            return null;
        }

        $plugin = new $class();

        if (!$plugin instanceof PluginInterface) {
            trigger_error(
                "Plugin class '$class' does not implement PluginInterface.",
                E_USER_WARNING
            );
            return null;
        }

        // Enforce that the plugin self-reports a slug matching its directory name.
        if ($plugin->getSlug() !== $slug) {
            trigger_error(
                "Plugin class '$class' reports slug '{$plugin->getSlug()}' but was loaded from directory '$slug'; skipping.",
                E_USER_WARNING
            );
            return null;
        }

        return $plugin;
    }

    /**
     * Converts a slug to the StudlyCase namespace segment used by the plugin class.
     * e.g. "my-plugin" → "TheatreCMS\Plugin\MyPlugin\Plugin"
     */
    private function resolveClass(string $slug): string
    {
        $studly = implode('', array_map('ucfirst', explode('-', $slug)));
        return 'TheatreCMS\\Plugin\\' . $studly . '\\Plugin';
    }

    /** Slugs must be lowercase alphanumeric with optional hyphens. */
    private function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug);
    }

    /** @param string[] $active */
    private function writeConfig(array $active): void
    {
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode(
            ['active' => array_values($active)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";

        // Write to a temp file first, then rename for an atomic replace.
        $tmp = $this->configFile . '.tmp.' . getmypid();
        file_put_contents($tmp, $json);
        rename($tmp, $this->configFile);
    }
}
