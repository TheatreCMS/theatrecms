<?php

use TheatreCMS\Controllers\PersonController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\PersonRepository;
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

        $group->post('/create', function (Request $request, Response $response) use ($container) {
            /** @var PersonController $controller */
            $controller = $container->get(PersonController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->store($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Person created successfully.' : 'Unable to create person. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->post('/edit', function (Request $request, Response $response) use ($container) {
            /** @var PersonController $controller */
            $controller = $container->get(PersonController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->update($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Person saved successfully.' : 'Unable to save person. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

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

