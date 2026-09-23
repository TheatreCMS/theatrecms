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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\DI\ServiceRegistrar;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\ImageSizeRegistry;
use TheatreCMS\Theme\MenuLocationRegistry;
use TheatreCMS\Theme\QueriedObject;
use TheatreCMS\Theme\ThemeManager;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/hooks.php';
require_once APP_ROOT . '/app/menu-locations.php';
require_once APP_ROOT . '/app/image-sizes.php';
require_once APP_ROOT . '/app/taxonomies.php';
require_once APP_ROOT . '/app/template-tags.php';

$container = new Container(require __DIR__ . '/settings.php');

$container->set(EntityManager::class, static function (Container $c): EntityManager {
    /** @var array $settings */
    $settings = $c->get('settings');

    // Use the ArrayAdapter or the FilesystemAdapter depending on the value of the 'dev_mode' setting
    // You can substitute the FilesystemAdapter for any other cache you prefer from the symfony/cache library
    $cache = $settings['doctrine']['dev_mode']
        ? new ArrayAdapter()
        : new FilesystemAdapter(directory: $settings['doctrine']['cache_dir']);

    $config = ORMSetup::createAttributeMetadataConfig(
        $settings['doctrine']['metadata_dirs'],
        $settings['doctrine']['dev_mode'],
        null,
        $cache,
    );
    $config->enableNativeLazyObjects(true);

    $connection = DriverManager::getConnection($settings['doctrine']['connection']);

    // Delight Auth owns the users table and all users_* tables.
    $connection->getConfiguration()->setSchemaAssetsFilter(static function (string|AbstractAsset $assetName): bool {
        if ($assetName instanceof AbstractAsset) {
            $assetName = $assetName->getName();
        }
        return !preg_match('~^users(?:_|$)~', $assetName);
    });
    return new EntityManager($connection, $config);
});

ServiceRegistrar::register($container);

$hookManager = $container->get(HookManager::class);
HookManager::setInstance($hookManager);

$menuLocationRegistry = $container->get(MenuLocationRegistry::class);
MenuLocationRegistry::setInstance($menuLocationRegistry);

$capabilityRegistry = $container->get(CapabilityRegistry::class);
CapabilityRegistry::setInstance($capabilityRegistry);
require_once APP_ROOT . '/app/capabilities.php';

$imageSizeRegistry = $container->get(ImageSizeRegistry::class);
ImageSizeRegistry::setInstance($imageSizeRegistry);
register_image_size('admin-thumbnail', 300, 300, true);

$taxonomyRegistry = $container->get(TaxonomyRegistry::class);
TaxonomyRegistry::setInstance($taxonomyRegistry);
register_taxonomy('genre', ['work'], [
    'label' => 'Genres',
    'singular_label' => 'Genre',
    'capability' => Capability::MANAGE_PEOPLE,
]);
register_taxonomy('post_category', ['post'], [
    'label' => 'Categories',
    'singular_label' => 'Category',
    'capability' => Capability::EDIT_POSTS,
]);

$queriedObject = $container->get(QueriedObject::class);
QueriedObject::setInstance($queriedObject);

$container->get(ThemeManager::class)->loadFunctions();

return $container;
