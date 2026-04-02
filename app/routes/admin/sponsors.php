<?php

use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\SponsorRepository;
use Delight\Auth\Auth;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The sponsors route group
 *
 * @var ContainerInterface $container
 */
if (isset($app)) {
    $app->group('/admin/sponsors', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', function (Request $request, Response $response) use ($container) {
            /** @var SponsorController $controller */
            $controller = $container->get(SponsorController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->store($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Sponsor created successfully.' : 'Unable to create sponsor. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->post('/edit', function (Request $request, Response $response) use ($container) {
            /** @var SponsorController $controller */
            $controller = $container->get(SponsorController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->update($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Sponsor saved successfully.' : 'Unable to save sponsor. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/sponsors/create.html.twig');
        })->add(new RequireTwigMiddleware($container));

        $group->get('/edit/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $repo = $container->get(SponsorRepository::class);

            $vars = [
                'sponsor' => $repo->fetch($args['id']),
            ];

            return $twig->render($response, 'admin/sponsors/edit.html.twig', $vars);
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($container) {
            $repo = $container->get(SponsorRepository::class);
            $sponsor = $repo->fetch(intval($args['id']));
            if ($sponsor) {
                $repo->delete($sponsor);
            }

            if ($request->getHeaderLine('HX-Request')) {
                $repo = $container->get(SponsorRepository::class);

                $vars = [
                    'sponsors' => $repo->fetchAll(),
                ];

                return $container->get(Twig::class)->render($response, 'admin/sponsors/_table.html.twig', $vars);
            }

            return $response->withHeader('Location', '/admin/sponsors');
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $repo = $container->get(SponsorRepository::class);

            return $twig->render($response, 'admin/sponsors/index.html.twig', [
                'sponsors' => $repo->fetchAll(),
            ]);
        });
    })->add(new RequireTwigMiddleware($container))->add($container->get(AuthMiddleware::class));
}

