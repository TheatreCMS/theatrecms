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
     * Register a callback to fire when an action tag is triggered.
     *
     * Actions are side-effect callbacks — unlike filters they do not transform
     * a value.  Internally they share the same HookManager storage as filters;
     * the return value is simply discarded when do_action() is called.
     *
     * @param string   $tag
     * @param callable $callback
     * @param int      $priority
     */
    function add_action(string $tag, callable $callback, int $priority = 10): void
    {
        HookManager::getInstance()->addFilter($tag, $callback, $priority);
    }
}

if (!function_exists('do_action')) {
    /**
     * Fire all callbacks registered for an action tag.
     *
     * Each handler receives $args directly; return values are discarded.
     *
     * @param string $tag
     * @param mixed  ...$args
     */
    function do_action(string $tag, mixed ...$args): void
    {
        HookManager::getInstance()->doAction($tag, ...$args);
    }
}
