<?php

use Delight\Auth\Role;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Auth\CapabilityRegistry;

$capabilities = CapabilityRegistry::getInstance();

$capabilities->register(Role::ADMIN, [
    Capability::MANAGE_EVENTS,
    Capability::MANAGE_MENUS,
    Capability::MANAGE_OPTIONS,
    Capability::MANAGE_PAGES,
    Capability::MANAGE_PEOPLE,
    Capability::MANAGE_POSTS,
    Capability::MANAGE_PRODUCTIONS,
    Capability::MANAGE_SEASONS,
    Capability::MANAGE_SPONSORS,
    Capability::MANAGE_USERS,
    Capability::MANAGE_VENUES,
    Capability::MANAGE_WORKS,
    Capability::SWITCH_THEMES,
    Capability::UPLOAD_FILES,
]);
