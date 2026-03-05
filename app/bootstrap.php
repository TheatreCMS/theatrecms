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

use Clubdeuce\TheatreCMS\Controllers\EventController;
use Clubdeuce\TheatreCMS\Controllers\LoginController;
use Clubdeuce\TheatreCMS\Controllers\ProductionController;
use Clubdeuce\TheatreCMS\Controllers\UsersController;
use Clubdeuce\TheatreCMS\Repositories\EventRepository;
use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Clubdeuce\TheatreCMS\Repositories\SponsorRepository;
use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Clubdeuce\TheatreCMS\Repositories\VenueRepository;
use Clubdeuce\TheatreCMS\Repositories\WorkRepository;
use Delight\Auth\Auth;
use DI\Container;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Slim\App;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;


if( !defined('APP_ROOT') )
    define ('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';

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

// Register Slim\Views\Twig in the container
$container->set(Twig::class, static function (Container $c): Twig {
    /** @var array $settings */
    $settings = $c->get('settings');
    $viewSettings = $settings['view'] ?? [];

    $templatePath = $viewSettings['template_path'] ?? APP_ROOT . '/templates';
    $cache = ($viewSettings['cache_enabled'] ?? false) ? ($viewSettings['cache'] ?? APP_ROOT . '/var/twig') : false;
    $debug = $viewSettings['debug'] ?? false;

    return Twig::create($templatePath, [
        'cache' => $cache,
        'debug' => $debug,
        'auto_reload' => $debug,
    ]);
});

// Register a callable factory for TwigMiddleware that can be invoked with the Slim\App
$container->set(TwigMiddleware::class, static function (Container $c): callable {
    return function (App $app, string $containerKey = 'view') {
        return TwigMiddleware::createFromContainer($app, $containerKey);
    };
});

$repositories = [
    EventRepository::class,
    PersonRepository::class,
    ProductionRepository::class,
    SeasonRepository::class,
    SponsorRepository::class,
    UserRepository::class,
    VenueRepository::class,
    WorkRepository::class,
    EventRepository::class,
];

foreach($repositories as $repository) {
    $container->set($repository, static function (Container $c) use ($repository) {
        return new $repository($c->get(EntityManager::class));
    });
}

$container->set(Auth::class, static function (Container $c) {
    return new Auth($c->get(EntityManager::class)->getConnection()->getNativeConnection());
});

// Add the Auth
$container->get(UserRepository::class)->setAuth($container->get(Auth::class));

$container->set(LoginController::class, static function (Container $c) {
    return new LoginController($c->get(UserRepository::class), $c->get(Twig::class), $c->get(Auth::class));
});

// Register UsersController
$container->set(UsersController::class, static function (Container $c) {
    return new UsersController($c->get(UserRepository::class), $c->get(Twig::class));
});

// Register ProductionController
$container->set(ProductionController::class, static function (Container $c) {
    return new ProductionController($c->get(ProductionRepository::class), $c->get(EntityManager::class));
});

// Register EventController
$container->set(EventController::class, static function (Container $c) {
    return new EventController($c->get(EventRepository::class), $c->get(EntityManager::class));
});

return $container;
