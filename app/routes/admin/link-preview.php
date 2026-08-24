<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\LinkPreviewController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;

if (isset($app)) {
    $app->group('/admin/link-preview', function ($group) {
        $group->get('/fetch', [LinkPreviewController::class, 'fetch']);
    })->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::UPLOAD_FILES))
      ->add($container->get(AuthMiddleware::class));
}
