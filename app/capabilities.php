<?php

use Delight\Auth\Role;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Auth\CapabilityRegistry;

$capabilities = CapabilityRegistry::getInstance();

$capabilities->register(Role::ADMIN, [
    Capability::MANAGE_USERS,
    Capability::MANAGE_OPTIONS,
    Capability::MANAGE_MENUS,
    Capability::UPLOAD_FILES,
    Capability::EDIT_POSTS,
    Capability::EDIT_PAGES,
    Capability::MANAGE_PRODUCTIONS,
    Capability::MANAGE_PEOPLE,
]);
