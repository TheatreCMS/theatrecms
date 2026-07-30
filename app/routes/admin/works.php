<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\WorksController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/works', function ($group) {
        $group->post('/create', [WorksController::class, 'store']);
        $group->get('/create', [WorksController::class, 'create']);
        $group->post('/edit', [WorksController::class, 'update']);
        $group->get('/edit/{id}', [WorksController::class, 'edit']);
        $group->delete('/{id}', [WorksController::class, 'destroy']);
        $group->get('', [WorksController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_PEOPLE))
      ->add($container->get(AuthMiddleware::class));
}
