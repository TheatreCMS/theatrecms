<?php

use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

if (isset($app)) {
    $app->group('/seasons', function ($group) {
        $container = $group->getContainer();

        $group->get('/{slug}/{productionSlug}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var  ProductionRepository $productionRepository */
            $productionRepository = $container->get(ProductionRepository::class);
            $production = $productionRepository->getBySlug($args['productionSlug']);

            if (!$production) {
                return $response->withStatus(404)->write('Production not found in this season');
            }

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'seasons/production.html.twig', ['production' => $production]);
        });
        $group->get('/{slug}', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetchBySlug($request->getAttribute('slug'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'seasons/show.html.twig', ['season' => $season]);
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $seasons = $repository->fetchAll();

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'seasons/list.html.twig', ['seasons' => $seasons]);
        });
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
