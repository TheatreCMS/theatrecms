<?php
/**
 *
 * Copyright (C) 2026  TheatreCMS Team (https://theatrecms.dev)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

use DI\Container;
use Delight\Auth\Auth;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\DI\ServiceRegistrar;
use TheatreCMS\Repositories\UserRepository;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\MenuLocationRegistry;


if( !defined('APP_ROOT') )
    define ('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/hooks.php';
require_once APP_ROOT . '/app/menu-locations.php';

$container = new Container(require __DIR__ . '/settings.php');

$container->set(EntityManager::class, static function (Container $c): EntityManager {
    /** @var array $settings */
    $settings = $c->get('settings');

    // Use the ArrayAdapter or the FilesystemAdapter depending on the value of the 'dev_mode' setting
    // You can substitute the FilesystemAdapter for any other cache you prefer from the symfony/cache library
    $cache = $settings['doctrine']['dev_mode'] ?
        new ArrayAdapter() :
        new FilesystemAdapter(directory: $settings['doctrine']['cache_dir']);

    $config = ORMSetup::createAttributeMetadataConfiguration(
        $settings['doctrine']['metadata_dirs'],
        $settings['doctrine']['dev_mode'],
        null,
        $cache
    );

    $connection = DriverManager::getConnection($settings['doctrine']['connection']);

    // ignore the users and users_? tables for schema updates since it's managed by the Auth library
    // and we don't want Doctrine trying to modify it
    $connection->getConfiguration()->setSchemaAssetsFilter(static function (string|AbstractAsset $assetName): bool {
        if ($assetName instanceof AbstractAsset) {
            $assetName = $assetName->getName();
        }
        return (bool) preg_match("~^(?!user|users_)~", $assetName);
    });
    return new EntityManager($connection, $config);
});

ServiceRegistrar::register($container);

// Add the Auth
$container->get(UserRepository::class)->setAuth($container->get(Auth::class));

$hookManager = $container->get(HookManager::class);
HookManager::setInstance($hookManager);

$menuLocationRegistry = $container->get(MenuLocationRegistry::class);
MenuLocationRegistry::setInstance($menuLocationRegistry);

$capabilityRegistry = $container->get(CapabilityRegistry::class);
CapabilityRegistry::setInstance($capabilityRegistry);
require_once APP_ROOT . '/app/capabilities.php';

return $container;
