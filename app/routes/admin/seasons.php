<?php

use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/seasons', function ($group) {
        $group->post('/create',   [SeasonController::class, 'store']);
        $group->get('/create',    [SeasonController::class, 'create']);
        $group->post('/edit',     [SeasonController::class, 'update']);
        $group->get('/edit/{id}', [SeasonController::class, 'edit']);
        $group->get('/{id}',      [SeasonController::class, 'show']);
        $group->delete('/{id}/featured-image', [SeasonController::class, 'removeFeaturedImage']);
        $group->delete('/{id}',   [SeasonController::class, 'destroy']);
        $group->get('',           [SeasonController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
