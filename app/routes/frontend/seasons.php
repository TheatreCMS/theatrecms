<?php

use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Clubdeuce\TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * @var TemplateResolver $resolver
 */

if (isset($app)) {
    $app->group('/seasons', function ($group) use ($resolver){
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

        $group->get('/{slug}', function (Request $request, Response $response) use ($container, $resolver) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetchBySlug($request->getAttribute('slug'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $template = $resolver->resolve($twig,
                'seasons/single-' . $season->getSlug() . '.html.twig',  // most specific
                'seasons/single.html.twig',
                'index.html.twig'                                         // fallback
            );

            return $twig->render($response, $template, ['season' => $season]);
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
