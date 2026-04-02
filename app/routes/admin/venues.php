<?php

use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\VenueRepository;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The venues route group
 *
 * @var ContainerInterface $container
 */

if (isset($app)) {
    $app->group('/admin/venues', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', function (Request $request, Response $response) use ($container) {
            /** @var VenueController $controller */
            $controller = $container->get(VenueController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->store($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Venue created successfully.' : 'Unable to create venue. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->post('/edit', function (Request $request, Response $response) use ($container) {
            /** @var VenueController $controller */
            $controller = $container->get(VenueController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->update($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Venue saved successfully.' : 'Unable to save venue. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/venues/create.html.twig');
        });

        $group->get('/edit/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $venueRepo = $container->get(VenueRepository::class);

            $vars = [
                'venue' => $venueRepo->fetch($args['id']),
            ];

            return $twig->render($response, 'admin/venues/edit.html.twig', $vars);
        });

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
        });
    })->add(new RequireTwigMiddleware($container))->add($container->get(AuthMiddleware::class));
}

