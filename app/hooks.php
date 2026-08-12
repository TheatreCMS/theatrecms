<?php

use TheatreCMS\Theme\HookManager;

if (!function_exists('add_filter')) {
    /**
     * Register a callback to run when a filter tag is applied.
     *
     * @param string   $tag
     * @param callable $callback
     * @param int      $priority
     */
    function add_filter(string $tag, callable $callback, int $priority = 10): void
    {
        HookManager::getInstance()->addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('apply_filters')) {
    /**
     * Execute callbacks registered against the provided tag.
     *
     * @param string $tag
     * @param mixed  $value
     * @param mixed  ...$args
     * @return mixed
     */
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        return HookManager::getInstance()->applyFilters($tag, $value, ...$args);
    }
}

if (!function_exists('add_action')) {
    /**
     * Register a callback to run when an action tag is executed.
     *
     * @param string   $tag
     * @param callable $callback
     * @param int      $priority
     */
    function add_action(string $tag, callable $callback, int $priority = 10): void
    {
        HookManager::getInstance()->addAction($tag, $callback, $priority);
    }
}

if (!function_exists('do_action')) {
    /**
     * Execute callbacks registered against the provided tag.
     *
     * @param string $tag
     * @param mixed  ...$args
     */
    function do_action(string $tag, mixed ...$args): void
    {
        HookManager::getInstance()->doAction($tag, ...$args);
    }
}
