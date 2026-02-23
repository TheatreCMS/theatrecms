<?php

use Clubdeuce\TheatreCMS\Controllers\SponsorController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\SponsorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

if (isset($app)) {
    $app->group('/admin/sponsors', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [SponsorController::class, 'store']);
        $group->post('/edit', [SponsorController::class, 'update']);

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
        })->add(new RequireTwigMiddleware($container));

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
        })->add(new AuthMiddleware());
    });
}

