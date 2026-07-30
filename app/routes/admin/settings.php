<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\SettingsController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/settings', function ($group) {
        $group->post('', [SettingsController::class, 'update']);
        $group->get('', [SettingsController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_OPTIONS))
      ->add($container->get(AuthMiddleware::class));
}
