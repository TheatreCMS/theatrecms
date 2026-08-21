<?php

use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Theme\TemplateResolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * @var TemplateResolver $resolver
 */

if (isset($app)) {
    $app->group('/seasons', function ($group) use ($resolver){
        $container = $group->getContainer();

        $group->get('/{slug}/{productionSlug}', function (Request $request, Response $response, array $args) use ($container, $resolver) {
            /** @var  ProductionRepository $productionRepository */
            $productionRepository = $container->get(ProductionRepository::class);
            $production = $productionRepository->getBySlug($args['productionSlug']);

            if (!$production) {
                $response->getBody()->write('Production not found in this season');

                return $response->withStatus(404);
            }

            $production = apply_filters('theatrecms/production', $production, $request, $args);

            /** @var EventRepository $eventRepository */
            $eventRepository = $container->get(EventRepository::class);
            $performances = $eventRepository->fetchByProduction($production->getId());

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $resolver->renderSingle($twig, $response, 'productions', $production->getSlug(), [
                'production' => $production,
                'performances' => $performances,
            ]);
        });

        $group->get('/{slug}', function (Request $request, Response $response, array $args) use ($container, $resolver ) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetchBySlug($args['slug']);

            if (!$season) {
                $response->getBody()->write('Season not found');

                return $response->withStatus(404);
            }

            $season = apply_filters('theatrecms/season', $season, $request, $args);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $resolver->renderSingle($twig, $response, 'seasons', $season->getSlug(), ['season' => $season]);
        });

        $group->get('', function (Request $request, Response $response) use ($container, $resolver) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $seasons = $repository->fetchAll();
            $seasons = apply_filters('theatrecms/seasons', $seasons, $request);

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $resolver->renderList($twig, $response, 'seasons', ['seasons' => $seasons]);
        });
    })->add(new RequireTwigMiddleware($app->getContainer()));
}
