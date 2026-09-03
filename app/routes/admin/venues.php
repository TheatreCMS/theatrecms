<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/venues', function ($group) {
        $group->post('/create',        [VenueController::class, 'store']);
        $group->get('/create',         [VenueController::class, 'create']);
        $group->post('/quick-create',  [VenueController::class, 'quickStore']);
        $group->get('/quick-create',   [VenueController::class, 'quickCreate']);
        $group->post('/edit',          [VenueController::class, 'update']);
        $group->get('/edit/{id}',      [VenueController::class, 'edit']);
        $group->delete('/{id}/featured-image', [VenueController::class, 'removeFeaturedImage']);
        $group->delete('/{id}',        [VenueController::class, 'destroy']);
        $group->get('',                [VenueController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_PRODUCTIONS))
      ->add($container->get(AuthMiddleware::class));
}
