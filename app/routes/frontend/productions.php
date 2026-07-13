<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\ProductionRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

if (isset($app)) {
    $app->get('/productions', function (Request $request, Response $response) use ($app) {
        $container = $app->getContainer();

        /** @var ProductionRepository $repository */
        $repository = $container->get(ProductionRepository::class);
        $productions = $repository->fetchAll();
        $productions = apply_filters('theatrecms/productions', $productions, $request);

        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return $twig->render($response, 'productions/list.html.twig', ['productions' => $productions]);
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
