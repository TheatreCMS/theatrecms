<?php
/**
 *
 * Copyright (C) 2026  TheatreCMS Team (https://theatrecms.dev)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
define('ROOT_DIR', dirname(__DIR__));
const APP_DIR = ROOT_DIR . '/app';
const ROUTES_DIR = APP_DIR . '/routes';

require_once ROOT_DIR . "/vendor/autoload.php";

use TheatreCMS\Controllers\LoginController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;

/**
 * @var Psr\Container\ContainerInterface $container
 */
$container = require ROOT_DIR . '/app/bootstrap.php';
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

// Slim doesn't treat "/admin" and "/admin/" as the same route by default, so a
// trailing slash 404s even though the non-trailing-slash route exists. Normalize
// it here: redirect GET requests to the canonical (no trailing slash) URL, and
// just rewrite the URI in place for other methods so a submitted form isn't lost.
$app->add(function (Request $request, RequestHandlerInterface $handler) {
    $uri = $request->getUri();
    $path = $uri->getPath();

    if ($path !== '/' && str_ends_with($path, '/')) {
        $newPath = rtrim($path, '/');

        if ($request->getMethod() === 'GET') {
            // Redirect with a path-only (no scheme/host/port) Location so this
            // still works correctly behind a reverse proxy (e.g. DDEV) that may
            // not report the externally-visible scheme/port back to Slim.
            $query = $uri->getQuery();
            $location = $newPath . ($query !== '' ? '?' . $query : '');

            return (new \Slim\Psr7\Response())
                ->withHeader('Location', $location)
                ->withStatus(301);
        }

        $request = $request->withUri($uri->withPath($newPath));
    }

    return $handler->handle($request);
});

$resolver = new TemplateResolver();

// Load external route files
require ROUTES_DIR . '/admin/seasons.php';
require ROUTES_DIR . '/admin/events.php';
require ROUTES_DIR . '/admin/people.php';
require ROUTES_DIR . '/admin/productions.php';
require ROUTES_DIR . '/admin/sponsors.php';
require ROUTES_DIR . '/admin/users.php';
require ROUTES_DIR . '/admin/profile.php';
require ROUTES_DIR . '/admin/venues.php';
require ROUTES_DIR . '/admin/works.php';
require ROUTES_DIR . '/admin/posts.php';
require ROUTES_DIR . '/admin/pages.php';
require ROUTES_DIR . '/admin/menus.php';
require ROUTES_DIR . '/admin/images.php';
require ROUTES_DIR . '/admin/settings.php';
require ROUTES_DIR . '/frontend/seasons.php';
require ROUTES_DIR . '/frontend/productions.php';
require ROUTES_DIR . '/frontend/people.php';
require ROUTES_DIR . '/frontend/works.php';

$app->get('/admin/login', [LoginController::class, 'login']);
$app->post('/admin/login', [LoginController::class, 'authenticate']);
$app->get('/admin/logout', [LoginController::class, 'logout']);
$app->post('/admin/register', [LoginController::class, 'register']);

$app->get('/admin', function (Request $request, Response $response) use ($container) {
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'admin/index.html.twig');
})->add(new RequireTwigMiddleware($container))
  ->add($container->get(AuthMiddleware::class));

require ROUTES_DIR . '/frontend/pages.php';

$app->get('/', function (Request $request, Response $response) use ($container) {
    $twig = $container->get(Twig::class);
    $posts = $container->get(PostRepository::class)->fetchPublished();
    $featuredProduction = $container->get(ProductionRepository::class)->findFeatured();

    $featuredProductionStatus = null;
    if ($featuredProduction) {
        $today = new \DateTime('today');
        $featuredProductionStatus = $featuredProduction->getOpening() && $featuredProduction->getOpening() <= $today
            ? 'now-playing'
            : 'upcoming';
    }

    return $twig->render($response, 'index.html.twig', [
        'posts' => $posts,
        'featuredProduction' => $featuredProduction,
        'featuredProductionStatus' => $featuredProductionStatus,
    ]);
});
$app->run();
