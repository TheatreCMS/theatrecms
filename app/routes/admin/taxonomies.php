<?php

use TheatreCMS\Controllers\TermController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

// No group-level RequireCapabilityMiddleware: each taxonomy declares its own capability,
// which TermController checks once it has resolved {taxonomy}.
if (isset($app)) {
    $app->group('/admin/taxonomies/{taxonomy}', function ($group) {
        $group->post('',               [TermController::class, 'store']);
        $group->get('/edit/{id}',      [TermController::class, 'edit']);
        $group->post('/edit/{id}',     [TermController::class, 'update']);
        $group->delete('/{id}',        [TermController::class, 'destroy']);
        $group->get('',                [TermController::class, 'index']);
    })->add(new RequireTwigMiddleware($container))
      ->add($container->get(AuthMiddleware::class));
}
