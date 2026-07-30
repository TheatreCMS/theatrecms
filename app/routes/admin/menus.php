<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\MenuController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/menus', function ($group) {
        $group->post('/create',     [MenuController::class, 'store']);
        $group->get('/create',      [MenuController::class, 'create']);
        $group->get('/edit/{id}',   [MenuController::class, 'edit']);
        $group->post('/{id}/items', [MenuController::class, 'saveTree']);
        $group->delete('/{id}',     [MenuController::class, 'destroy']);
        $group->get('',             [MenuController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_MENUS))
      ->add($container->get(AuthMiddleware::class));
}
