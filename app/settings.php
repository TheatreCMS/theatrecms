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

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

use Symfony\Component\Yaml\Yaml;

$configFile = APP_ROOT . '/app/config.yaml';
$config = file_exists($configFile) ? (Yaml::parseFile($configFile) ?? []) : [];

$defaults = [
    'siteTitle'    => 'TheatreCMS',
    'siteTimezone' => 'UTC',
];

$appConfig = array_merge($defaults, $config);

return [
    'settings' => [
        'app' => $appConfig,
        'slim' => [
            // Returns a detailed HTML page with error details and
            // a stack trace. Should be disabled in production.
            'displayErrorDetails' => true,

            // Whether to display errors on the internal PHP log or not.
            'logErrors' => true,

            // If true, display full errors with message and stack trace on the PHP log.
            // If false, display only "Slim Application Error" on the PHP log.
            // Doesn't do anything when 'logErrors' is false.
            'logErrorDetails' => true,
        ],

        'doctrine' => [
            // Enables or disables Doctrine metadata caching
            // for either performance or convenience during development.
            'dev_mode' => true,

            // Path where Doctrine will cache the processed metadata
            // when 'dev_mode' is false.
            'cache_dir' => APP_ROOT . '/var/doctrine',

            // List of paths where Doctrine will search for metadata.
            // Metadata can be either YML/XML files or PHP classes annotated
            // with comments or PHP8 attributes.
            'metadata_dirs' => [APP_ROOT . '/src/Models'],

            // The parameters Doctrine needs to connect to your database.
            // These parameters depend on the driver (for instance the 'pdo_sqlite' driver
            // needs a 'path' parameter and doesn't use most of the ones shown in this example).
            // Refer to the Doctrine documentation to see the full list
            // of valid parameters: https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html
            'connection' => [
                'driver' => 'pdo_mysql',
                'host' => 'db',
                'port' => 3306,
                'dbname' => 'db',
                'user' => 'db',
                'password' => 'db',
                'charset' => 'utf8mb4'
            ]
        ],

        // Twig view settings. Template path and cache may be customized per-environment.
        'view' => [
            // Path (or array of paths) where Twig will look for templates
            'template_path' => APP_ROOT . '/templates',

            // Path for twig cache. Set to false or leave empty to disable caching in dev.
            'cache' => APP_ROOT . '/var/twig',

            // Whether Twig caching should be enabled. You can tie this to 'doctrine.dev_mode' if desired.
            'cache_enabled' => false,

            // Enable Twig debug mode (adds debug extension)
            'debug' => true,
        ],
        'themes' => [
            // Directory where themes are stored. Each theme should be in its own subdirectory.
            'dir' => APP_ROOT . '/www/themes',

            // The active theme to use. Should correspond to a subdirectory in the themes dir.
            'active' => $appConfig['themes']['active'] ?? 'default',
        ]
    ]
];
