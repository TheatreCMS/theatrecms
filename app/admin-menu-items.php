<?php

use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Admin\AdminMenuRegistry;
use TheatreCMS\Auth\Capability;

// Core admin sidebar: Dashboard on its own, then content types, then site administration.
// Plugin sections without an explicit position land between Appearance and Administration.
$adminMenu = AdminMenuRegistry::getInstance();

$adminMenu->registerGroup(null, 0);
$adminMenu->registerGroup('Content', 10);
$adminMenu->registerGroup('Appearance', 20);
$adminMenu->registerGroup('Administration', 90);

$adminMenu->add(new AdminMenuItem('Dashboard', 'admin', null, null, 10, exact: true));

$adminMenu->add(new AdminMenuItem('Themes', 'admin/themes', Capability::SWITCH_THEMES, 'Appearance', 10));
$adminMenu->add(new AdminMenuItem('Menus',  'admin/menus',  Capability::MANAGE_MENUS,  'Appearance', 20));

$adminMenu->add(new AdminMenuItem('Users',    'admin/users',    Capability::MANAGE_USERS,   'Administration', 10));
$adminMenu->add(new AdminMenuItem('Settings', 'admin/settings', Capability::MANAGE_OPTIONS, 'Administration', 20));
