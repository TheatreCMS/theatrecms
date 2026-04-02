<?php

use TheatreCMS\Controllers\PersonController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/people', function ($group) {
        $group->post('/create',   [PersonController::class, 'store']);
        $group->get('/create',    [PersonController::class, 'create']);
        $group->post('/edit',     [PersonController::class, 'update']);
        $group->get('/edit/{id}', [PersonController::class, 'edit']);
        $group->delete('/{id}',   [PersonController::class, 'destroy']);
        $group->get('',           [PersonController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
