<?php

use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/productions', function ($group) {
        $group->post('/create',   [ProductionController::class, 'store']);
        $group->get('/create',    [ProductionController::class, 'create']);
        $group->post('/edit',     [ProductionController::class, 'update']);
        $group->get('/edit/{id}', [ProductionController::class, 'edit']);
        $group->delete('/{id}',   [ProductionController::class, 'destroy']);
        $group->get('',           [ProductionController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
