<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/productions', function ($group) {
        $group->post('/create', [ProductionController::class, 'store']);
        $group->get('/create', [ProductionController::class, 'create']);
        $group->post('/edit', [ProductionController::class, 'update']);
        $group->get('/edit/{id}', [ProductionController::class, 'edit']);
        $group->delete('/{id}/featured-image', [ProductionController::class, 'removeFeaturedImage']);
        $group->delete('/{id}', [ProductionController::class, 'destroy']);
        $group->get('', [ProductionController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_PRODUCTIONS))
      ->add($container->get(AuthMiddleware::class));
}
