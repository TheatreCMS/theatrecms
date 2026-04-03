<?php

use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Middleware\AuthMiddleware;

if (isset($app)) {
    $app->group('/admin/images', function ($group) {
        $group->post('/upload', [ImageUploadController::class, 'upload']);
    })->add($container->get(AuthMiddleware::class));
}
