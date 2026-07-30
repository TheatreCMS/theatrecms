<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\EventController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/events', function ($group) {
        $group->post('/bulk-create', [EventController::class, 'storeRecurring']);
        $group->post('/create', [EventController::class, 'store']);
        $group->get('/create', [EventController::class, 'create']);
        $group->post('/edit', [EventController::class, 'update']);
        $group->get('/edit/{id}', [EventController::class, 'edit']);
        $group->delete('/{id}', [EventController::class, 'destroy']);
        $group->get('', [EventController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
        ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_PRODUCTIONS))
        ->add($container->get(AuthMiddleware::class));
}
