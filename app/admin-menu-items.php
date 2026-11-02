<?php

use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Admin\AdminMenuRegistry;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Auth\Capability;

// Core admin sidebar: Dashboard on its own, then content types, then site administration.
// Plugin sections without an explicit position land between Appearance and Administration.
$adminMenu = AdminMenuRegistry::getInstance();
$contentTypeRegistry = ContentTypeRegistry::getInstance();

$adminMenu->registerGroup(null, 0);
$adminMenu->registerGroup('Content', 10);
$adminMenu->registerGroup('Appearance', 20);
$adminMenu->registerGroup('Administration', 90);

$adminMenu->add(new AdminMenuItem('Dashboard', 'admin', null, null, 10, exact: true, icon: 'fa-solid fa-gauge'));

$adminMenu->add(new AdminMenuItem('Themes', 'admin/themes', Capability::SWITCH_THEMES, 'Appearance', 10, icon: 'fa-solid fa-palette'));
$adminMenu->add(new AdminMenuItem('Menus',  'admin/menus',  Capability::MANAGE_MENUS,  'Appearance', 20, icon: 'fa-solid fa-bars'));

$adminMenu->add(new AdminMenuItem('Users',    'admin/users',    Capability::MANAGE_USERS,   'Administration', 10, icon: 'fa-solid fa-users'));
$adminMenu->add(new AdminMenuItem('Settings', 'admin/settings', Capability::MANAGE_OPTIONS, 'Administration', 20, icon: 'fa-solid fa-gear'));

foreach ($contentTypeRegistry->all() as $type => $definition) {
    $adminMenu->add(new AdminMenuItem(
        $definition->label,
        $definition->adminSlug(),
        $definition->capability,
        'Content',
        10,
        icon: $definition->menuIcon,
    ));
}