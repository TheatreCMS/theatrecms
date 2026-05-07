<?php

use TheatreCMS\Controllers\SettingsController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/settings', function ($group) {
        $group->post('', [SettingsController::class, 'update']);
        $group->get('', [SettingsController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
