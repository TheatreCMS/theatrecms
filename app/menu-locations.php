<?php

use TheatreCMS\Theme\MenuLocationRegistry;

if (!function_exists('register_menu_location')) {
    /**
     * Declares a menu location the active theme supports (e.g. 'primary', 'footer'),
     * so admins can assign a menu to it from the Menus admin screen.
     *
     * @param string $slug
     * @param string $label
     */
    function register_menu_location(string $slug, string $label): void
    {
        MenuLocationRegistry::getInstance()->register($slug, $label);
    }
}
