<?php

namespace TheatreCMS\Plugin;

interface PluginDiscovery
{
    /**
     * @return PluginDescriptor[] installed plugins, sorted by package name
     */
    public function discover(): array;
}
