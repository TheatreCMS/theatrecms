<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\ThemesController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/themes', function ($group) {
        $group->get('', [ThemesController::class, 'index']);
        $group->post('/activate', [ThemesController::class, 'activate']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::SWITCH_THEMES))
      ->add($container->get(AuthMiddleware::class));
}
