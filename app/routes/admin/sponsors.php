<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/sponsors', function ($group) {
        $group->post('/create',        [SponsorController::class, 'store']);
        $group->get('/create',         [SponsorController::class, 'create']);
        $group->post('/quick-create',  [SponsorController::class, 'quickStore']);
        $group->get('/quick-create',   [SponsorController::class, 'quickCreate']);
        $group->post('/edit',          [SponsorController::class, 'update']);
        $group->get('/edit/{id}',      [SponsorController::class, 'edit']);
        $group->delete('/{id}/logo',   [SponsorController::class, 'removeLogo']);
        $group->delete('/{id}',        [SponsorController::class, 'destroy']);
        $group->get('',                [SponsorController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::MANAGE_PRODUCTIONS))
      ->add($container->get(AuthMiddleware::class));
}
