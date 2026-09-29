<?php

namespace TheatreCMS\Plugin;

/**
 * An installed plugin package, as found by a PluginDiscovery before it is instantiated.
 */
final class PluginDescriptor
{
    /**
     * @param string $package Composer package name (e.g. `theatrecms/dso-plugin`)
     * @param string $class   Fully qualified plugin class from `extra.theatrecms.plugin`
     * @param string $path    Package install directory
     */
    public function __construct(
        public readonly string $package,
        public readonly string $class,
        public readonly string $path,
    ) {
    }
}
