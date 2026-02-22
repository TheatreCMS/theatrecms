<?php


use Clubdeuce\TheatreCMS\Controllers\WorksController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The works route group
 */

if (isset($app)) {
    $app->group('/admin/works', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [WorksController::class, 'store']);

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/works/create.html.twig');
        })->add(new RequireTwigMiddleware($container));

        $group->get('/edit/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $worksRepository = $container->get(WorksController::class)->repository();

            return $twig->render($response, 'admin/works/edit.html.twig', [
                'work' => $worksRepository->fetch($args['id']),
            ]);
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $worksRepository = $container->get(WorksController::class)->repository();

            return $twig->render($response, 'admin/works/index.html.twig', [
                'works' => $worksRepository->fetchAll(),
            ]);
        });
    })->add(new AuthMiddleware());
}