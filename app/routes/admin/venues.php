<?php

use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/venues', function ($group) {
        $group->post('/create',   [VenueController::class, 'store']);
        $group->get('/create',    [VenueController::class, 'create']);
        $group->post('/edit',     [VenueController::class, 'update']);
        $group->get('/edit/{id}', [VenueController::class, 'edit']);
        $group->delete('/{id}',   [VenueController::class, 'destroy']);
        $group->get('',           [VenueController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
