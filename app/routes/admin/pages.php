<?php

use TheatreCMS\Controllers\PageController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/pages', function ($group) {
        $group->post('/create',   [PageController::class, 'store']);
        $group->get('/create',    [PageController::class, 'create']);
        $group->post('/edit',     [PageController::class, 'update']);
        $group->get('/edit/{id}', [PageController::class, 'edit']);
        $group->delete('/{id}',   [PageController::class, 'destroy']);
        $group->get('',           [PageController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
