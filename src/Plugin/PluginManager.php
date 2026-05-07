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

        return is_array($data['active'] ?? null) ? $data['active'] : [];
    }

    /**
     * Phase 1 — instantiate every active plugin and call register().
     *
     * Missing or invalid plugins emit E_USER_WARNING and are skipped so that
     * one broken plugin does not prevent the application from booting.
     */
    public function registerAll(Container $container): void
    {
        foreach ($this->getActiveSlugs() as $slug) {
            $plugin = $this->loadPlugin($slug);
            if ($plugin === null) {
                continue;
            }
            $this->loaded[$slug] = $plugin;
            $plugin->register($container);
        }
    }

    /**
     * Phase 2 — call boot() on every already-registered plugin.
     *
     * Must be called after registerAll() and after the Slim App is created.
     */
    public function bootAll(App $app): void
    {
        foreach ($this->loaded as $plugin) {
            $plugin->boot($app);
        }
    }

    /**
     * Adds a slug to the active list and persists the config file.
     *
     * @throws RuntimeException if the plugin's Plugin.php is missing.
     */
    public function activate(string $slug): void
    {
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
     * Returns metadata from plugin.json for a given slug, or [] if absent.
     *
     * @return array<string, string>
     */
    public function getMetadata(string $slug): array
    {
        $metaFile = $this->pluginsDir . '/' . $slug . '/plugin.json';
        if (!file_exists($metaFile)) {
            return [];
        }

        return json_decode((string) file_get_contents($metaFile), true) ?? [];
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
        $pluginFile = $this->pluginsDir . '/' . $slug . '/Plugin.php';

        if (!file_exists($pluginFile)) {
            trigger_error(
                "Plugin '$slug' listed in plugins.json but '$pluginFile' was not found.",
                E_USER_WARNING
            );
            return null;
        }

        require_once $pluginFile;

        $class = $this->resolveClass($slug);

        if (!class_exists($class)) {
            trigger_error(
                "Plugin class '$class' not found after requiring '$pluginFile'.",
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

        return $plugin;
    }

    /** Converts slug "example" → "TheatreCMS\Plugin\Example\Plugin" */
    private function resolveClass(string $slug): string
    {
        return 'TheatreCMS\\Plugin\\' . ucfirst($slug) . '\\Plugin';
    }

    /** @param string[] $active */
    private function writeConfig(array $active): void
    {
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->configFile,
            json_encode(
                ['active' => array_values($active)],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );
    }
}
