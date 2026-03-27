<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Controllers\PageController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\PageRepository;

if (isset($app)) {
    $app->group('/admin/pages', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [PageController::class, 'store']);

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/pages/create.html.twig');
        })->add(new RequireTwigMiddleware($container));

        $group->post('/edit', [PageController::class, 'update']);

        $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {
            /** @var PageRepository $repository */
            $repository = $container->get(PageRepository::class);
            $page = $repository->fetch($request->getAttribute('id'));

            if (!$page) {
                return $response->withStatus(404);
            }

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/pages/edit.html.twig', [
                'page' => $page,
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->delete('/{id}', function (Request $request, Response $response) use ($container) {
            /** @var PageRepository $repository */
            $repository = $container->get(PageRepository::class);
            $page = $repository->fetch($request->getAttribute('id'));

            if ($page) {
                try {
                    $repository->delete($page);
                } catch (\Exception $e) {
                    trigger_error("Unable to delete page: {$e->getMessage()}");
                }
            }

            $pages = $repository->fetchAll();

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            if ($request->getHeaderLine('HX-Request')) {
                return $twig->render($response, 'admin/pages/_table.html.twig', [
                    'pages' => $pages,
                ]);
            }

            return $twig->render($response, 'admin/pages/index.html.twig', [
                'pages' => $pages,
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var PageRepository $repository */
            $repository = $container->get(PageRepository::class);
            $pages = $repository->fetchAll();

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/pages/index.html.twig', [
                'pages' => $pages,
            ]);
        })->add(new RequireTwigMiddleware($container));
    })->add($app->getContainer()->get(AuthMiddleware::class));
}
