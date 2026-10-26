<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\EntryController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

/**
 * @var Slim\App $app
 * @var DI\Container $container
 */
if (isset($app)) {
    $app->group('/admin/entries', function ($group) {
        $group->post('/create', [EntryController::class, 'store']);
        $group->get('/create', [EntryController::class, 'create']);
        $group->post('/edit', [EntryController::class, 'update']);
        $group->get('/edit/{id}', [EntryController::class, 'edit']);
        $group->delete('/{id}', [EntryController::class, 'destroy']);
        $group->get('', [EntryController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
        ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_PRODUCTIONS))
        ->add($container->get(AuthMiddleware::class));
}
