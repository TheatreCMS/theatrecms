<?php

use TheatreCMS\Controllers\PostController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/posts', function ($group) {
        $group->post('/create',   [PostController::class, 'store']);
        $group->get('/create',    [PostController::class, 'create']);
        $group->post('/edit',     [PostController::class, 'update']);
        $group->get('/edit/{id}', [PostController::class, 'edit']);
        $group->delete('/{id}/featured-image', [PostController::class, 'removeFeaturedImage']);
        $group->delete('/{id}',   [PostController::class, 'destroy']);
        $group->get('',           [PostController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
