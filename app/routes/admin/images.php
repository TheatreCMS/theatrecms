<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\ImagesController;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

if (isset($app)) {
    $app->group('/admin/images', function ($group) {
        $group->post('/upload', [ImageUploadController::class, 'upload']);

        $group->get('', [ImagesController::class, 'index']);
        $group->get('/picker', [ImagesController::class, 'picker']);
        $group->post('/library-upload', [ImagesController::class, 'upload']);
        $group->get('/{id}/select', [ImagesController::class, 'select']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::UPLOAD_FILES))
      ->add($container->get(AuthMiddleware::class));
}
