<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\MediaController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

// Reuses UPLOAD_FILES for the whole group (browse/upload/delete alike): the
// app only has one role today and RequireCapabilityMiddleware only supports
// one capability per route group. A MANAGE_MEDIA/UPLOAD_FILES split is a
// natural next step once a second, less-privileged role exists.
if (isset($app)) {
    $app->group('/admin/media', function ($group) {
        $group->get('', [MediaController::class, 'index']);
        $group->get('/picker', [MediaController::class, 'picker']);
        $group->post('/library-upload', [MediaController::class, 'upload']);
        $group->get('/{id}/select', [MediaController::class, 'select']);
        $group->get('/{id}', [MediaController::class, 'show']);
        $group->patch('/{id}', [MediaController::class, 'update']);
        $group->delete('/{id}', [MediaController::class, 'destroy']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::UPLOAD_FILES))
      ->add($container->get(AuthMiddleware::class));
}
