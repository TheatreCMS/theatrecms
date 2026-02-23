<?php

use Clubdeuce\TheatreCMS\Controllers\VenueController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\VenueRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

if (isset($app)) {
    $app->group('/admin/venues', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [VenueController::class, 'store']);
        $group->post('/edit', [VenueController::class, 'update']);

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/venues/create.html.twig');
        })->add(new RequireTwigMiddleware($container));

        $group->get('/edit/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $venueRepo = $container->get(VenueRepository::class);

            $vars = [
                'venue' => $venueRepo->fetch($args['id']),
            ];

            return $twig->render($response, 'admin/venues/edit.html.twig', $vars);
        })->add(new RequireTwigMiddleware($container));

        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($container) {
            $repo  = $container->get(VenueRepository::class);
            $venue = $repo->fetch(intval($args['id']));
            if ($venue) {
                $repo->delete($venue);
            }

            if ($request->getHeaderLine('HX-Request')) {
                $venueRepo = $container->get(VenueRepository::class);

                $vars = [
                    'venues' => $venueRepo->fetchAll(),
                ];

                return $container->get(Twig::class)->render($response, 'admin/venues/_table.html.twig', $vars);
            }

            return $response->withHeader('Location', '/admin/venues');
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $venueRepo = $container->get(VenueRepository::class);

            return $twig->render($response, 'admin/venues/index.html.twig', [
                'venues' => $venueRepo->fetchAll(),
            ]);
        })->add(new AuthMiddleware());
    });
}

