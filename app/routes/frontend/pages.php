<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\PageRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

if (isset($app)) {
    $app->get('/{slug}', function (Request $request, Response $response, array $args) use ($app) {
        $container = $app->getContainer();

        /** @var PageRepository $repository */
        $repository = $container->get(PageRepository::class);
        $page = $repository->fetchBySlug($args['slug']);

        if (!$page) {
            $response->getBody()->write('Page not found');

            return $response->withStatus(404);
        }

        $page = apply_filters('theatrecms/page', $page, $request, $args);

        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return $twig->render($response, 'pages/single.html.twig', ['page' => $page]);
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
