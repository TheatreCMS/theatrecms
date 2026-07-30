<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;

if (isset($app)) {
    $app->group('/admin/images', function ($group) {
        $group->post('/upload', [ImageUploadController::class, 'upload']);
    })->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::UPLOAD_FILES))
      ->add($container->get(AuthMiddleware::class));
}
