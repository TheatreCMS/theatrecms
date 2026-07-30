<?php

use TheatreCMS\Controllers\ProfileController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/profile', function ($group) {
        $group->post('', [ProfileController::class, 'update']);
        $group->get('', [ProfileController::class, 'edit']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
