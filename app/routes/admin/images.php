<?php

use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;

// This route is frozen: it serves the EditorJS in-body "Image Gallery"/
// "Carousel" block upload endpoint, whose JSON contract (field name `image`,
// {success, file:{url}}/{success:0, error:{message}}) is depended on by the
// vendored @editorjs/image tool and the hand-written gallery/carousel tools.
// Everything else that used to live under /admin/images now lives under
// /admin/media -- see app/routes/admin/media.php.
if (isset($app)) {
    $app->group('/admin/images', function ($group) {
        $group->post('/upload', [ImageUploadController::class, 'upload']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), Capability::UPLOAD_FILES))
      ->add($container->get(AuthMiddleware::class));
}
