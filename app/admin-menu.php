<?php

use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Admin\AdminMenuRegistry;

if (!function_exists('register_admin_menu_item')) {
    /**
     * Adds a link to the admin sidebar.
     *
     * @param string      $label      Link text
     * @param string      $url        Path relative to the site root, e.g. `admin/news`
     * @param string|null $capability Capability required to see the item; null means any signed-in user
     * @param string|null $group      Sidebar section (e.g. `Content`); a new label creates a new section
     * @param int         $position   Sort order within the section (core items use 10–100)
     */
    function register_admin_menu_item(
        string $label,
        string $url,
        ?string $capability = null,
        ?string $group = 'Content',
        int $position = 100,
    ): void {
        AdminMenuRegistry::getInstance()->add(new AdminMenuItem($label, $url, $capability, $group, $position));
    }
}
