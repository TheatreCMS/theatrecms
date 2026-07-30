<?php

use Delight\Auth\Role;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Auth\CapabilityRegistry;

$capabilities = CapabilityRegistry::getInstance();

$capabilities->register(Capability::MANAGE_USERS, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_OPTIONS, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_MENUS, [Role::ADMIN]);
$capabilities->register(Capability::UPLOAD_FILES, [Role::ADMIN]);
$capabilities->register(Capability::EDIT_POSTS, [Role::ADMIN]);
$capabilities->register(Capability::EDIT_PAGES, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_PRODUCTIONS, [Role::ADMIN]);
$capabilities->register(Capability::MANAGE_PEOPLE, [Role::ADMIN]);
