<?php

use TheatreCMS\Controllers\UsersController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/users', function ($group) {
        $group->post('/create',   [UsersController::class, 'store']);
        $group->get('/create',    [UsersController::class, 'create']);
        $group->post('/edit',     [UsersController::class, 'update']);
        $group->get('/edit/{id}', [UsersController::class, 'edit']);
        $group->delete('/{id}',   [UsersController::class, 'destroy']);
        $group->get('',           [UsersController::class, 'index']);
    })->add($container->get(AuthMiddleware::class))
      ->add($container->get(RequireTwigMiddleware::class));
}
