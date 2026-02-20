<?php

use Clubdeuce\TheatreCMS\Controllers\SeasonController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The seasons route group
 */

if (isset($app)) {
    $app->group('/admin/seasons', function ($group) {
        $container = $group->getContainer();
        $group->get('/delete/{id}', [SeasonController::class, 'delete']);

        $group->post('/create', [SeasonController::class, 'store']);
        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/seasons/create.html.twig');
        })->add(new RequireTwigMiddleware($container));

        $group->post('/edit', [SeasonController::class, 'update']);
        $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetch($request->getAttribute('id'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/seasons/edit.html.twig', ['season' => $season]);
        })->add(new RequireTwigMiddleware($container));

        $group->get('/{id}', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(SeasonRepository::class);
            /** @var SeasonRepository $repository */
            $season = $repository->fetch($request->getAttribute('id'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'seasons/show.html.twig', ['season' => $season]);
        })->add(new RequireTwigMiddleware($container));

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            /** @var SeasonController $seasonController */
            $seasonController = $container->get(SeasonController::class);
            $seasons = $seasonController->repository()->fetchAll();

            return $twig->render($response, 'admin/seasons/index.html.twig', ['seasons' => $seasons]);
        })->add(new RequireTwigMiddleware($container));
    })->add(new AuthMiddleware());
}