<?php

use Clubdeuce\TheatreCMS\Controllers\PeopleController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The people route group
 *
 * @var ContainerInterface $container
 */
if (isset($app)) {
    $app->group('/admin/people', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [PeopleController::class, 'store']);
        $group->post('/edit', [PeopleController::class, 'update']);

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/people/create.html.twig');
        });

        $group->get('/edit/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $personRepo = $container->get(PersonRepository::class);

            $vars = [
                'person' => $personRepo->fetch($args['id']),
            ];

            return $twig->render($response, 'admin/people/edit.html.twig', $vars);
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($container) {
            $repo = $container->get(PersonRepository::class);
            $person = $repo->fetch(intval($args['id']));
            if ($person) {
                $repo->delete($person);
            }

            return $response->withHeader('Location', '/admin/people');
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $personRepo = $container->get(PersonRepository::class);

            return $twig->render($response, 'admin/people/index.html.twig', [
                'people' => $personRepo->fetchAll(),
            ]);
        });
    })->add(new RequireTwigMiddleware($container))->add($container->get(AuthMiddleware::class));
}

