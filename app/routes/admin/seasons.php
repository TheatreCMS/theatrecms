<?php

use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The seasons route group
 */

if (isset($app)) {
    $app->group('/admin/seasons', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [SeasonController::class, 'store']);
        $group->get('/create', function (Request $request, Response $response) use ($container) {
            $sponsorsRepository = $container->get(SponsorRepository::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/seasons/create.html.twig', [
                'sponsors' => $sponsorsRepository->fetchAll(),
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->post('/edit', [SeasonController::class, 'update']);
        $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(SeasonRepository::class);
            $sponsorsRepository = $container->get(SponsorRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetch($request->getAttribute('id'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/seasons/edit.html.twig', [
                'season' => $season,
                'sponsors' => $sponsorsRepository->fetchAll(),
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->get('/{id}', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetch($request->getAttribute('id'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'seasons/show.html.twig', ['season' => $season]);
        })->add(new RequireTwigMiddleware($container));

        $group->delete('/{id}', function (Request $request, Response $response) use ($container) {
            /** @var SeasonRepository $repository */
            $repository = $container->get(SeasonRepository::class);
            $season     = $repository->fetch($request->getAttribute('id'));

            try {
                if ($season) {
                    $repository->delete($season);
                }
            } catch (Exception $e) {
                trigger_error("Unable to delete production: {$e->getMessage()}");
            }

            $data = [
                'seasons'  => $repository->fetchAll()
            ];

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            if ($request->getHeaderLine('HX-Request'))
                return $twig->render($response, 'admin/seasons/_table.html.twig', $data);


            return $twig->render($response, 'admin/seasons/index.html.twig', $data);

        })->add(new RequireTwigMiddleware($container));

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            /** @var SeasonController $seasonController */
            $seasonController = $container->get(SeasonController::class);
            $seasons = $seasonController->repository()->fetchAll();

            return $twig->render($response, 'admin/seasons/index.html.twig', ['seasons' => $seasons]);
        })->add(new RequireTwigMiddleware($container));
    })->add($app->getContainer()->get(AuthMiddleware::class));
}
