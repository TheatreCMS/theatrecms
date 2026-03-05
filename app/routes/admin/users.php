<?php

use Clubdeuce\TheatreCMS\Controllers\UsersController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Delight\Auth\Auth;
use Delight\Auth\UnknownIdException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The users route group
 *
 * @var ContainerInterface $container
 */
if (isset($app)) {
    $app->group('/admin/users', function ($group) use ($container){
        $group->post('/create', [UsersController::class, 'store']);
        $group->post('/edit', [UsersController::class, 'update']);
        $group->delete('/{id}', function (Request $request, Response $response, array $args) use ($container) {
            /** @var Auth $auth */
            $auth = $container->get(Auth::class);
            /** @var UserRepository $repository */
            $repository = $container->get(UserRepository::class);

            try {
                // Use the route parameter provided by the DELETE request rather than relying on $_POST
                $id = (int) ($args['id'] ?? 0);

                $auth->admin()->deleteUserById($id);

                if ($request->getHeaderLine('HX-Request')) {
                    $vars = [
                        'users' => $repository->fetchAll(),
                    ];

                    return $container->get(Twig::class)->render($response, 'admin/users/_table.html.twig', $vars);
                }

                // Redirect back to the users index (was incorrectly pointing to sponsors)
                return $response->withHeader('Location', '/admin/users')->withStatus(302);
            }
            catch (UnknownIdException $e) {
                $response->getBody()->write($e->getMessage());
                return $response->withStatus(400);
            }
        });

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/users/create.html.twig');
        });

        // Add leading slash to route and accept $args so we can read the id consistently
        $group->get('/edit/{id}', function (Request $request, Response $response, array $args) use ($container) {
            $twig       = $container->get(Twig::class);
            $repository = $container->get(UserRepository::class);
            $user       = $repository->fetch($args['id']);

            return $twig->render($response, 'admin/users/edit.html.twig', ['user' => $user]);
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            $twig        = $container->get(Twig::class);
            $repository  = $container->get(UserRepository::class);
            $users       = $repository->fetchAll();
            $currentUser = $container->get(Auth::class)->getUserId();

            return $twig->render($response, 'admin/users/index.html.twig', ['users' => $users, 'currentUserId' => $currentUser]);
        });
    })->add($container->get(AuthMiddleware::class))->add($container->get(RequireTwigMiddleware::class));
}