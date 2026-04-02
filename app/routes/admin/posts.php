<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use TheatreCMS\Controllers\PostController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Enums\PostStatus;
use TheatreCMS\Repositories\PostRepository;

if (isset($app)) {
    $app->group('/admin/posts', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', function (Request $request, Response $response) use ($container) {
            /** @var PostController $controller */
            $controller = $container->get(PostController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->store($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Post created successfully.' : 'Unable to create post. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/posts/create.html.twig', [
                'statuses' => PostStatus::labels(),
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->post('/edit', function (Request $request, Response $response) use ($container) {
            /** @var PostController $controller */
            $controller = $container->get(PostController::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $result = $controller->update($request, $response);

            if ($request->getHeaderLine('HX-Request')) {
                $success = $result->getStatusCode() < 400;
                $response->getBody()->write($twig->fetch('admin/partials/_alert.html.twig', [
                    'type'    => $success ? 'success' : 'error',
                    'message' => $success ? 'Post saved successfully.' : 'Unable to save post. Please check your input.',
                ]));
                return $response;
            }

            return $result;
        });

        $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {
            /** @var PostRepository $repository */
            $repository = $container->get(PostRepository::class);
            $post = $repository->fetch($request->getAttribute('id'));

            if (!$post) {
                return $response->withStatus(404);
            }

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/posts/edit.html.twig', [
                'post' => $post,
                'statuses' => PostStatus::labels(),
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->delete('/{id}', function (Request $request, Response $response) use ($container) {
            /** @var PostRepository $repository */
            $repository = $container->get(PostRepository::class);
            $post = $repository->fetch($request->getAttribute('id'));

            if ($post) {
                try {
                    $repository->delete($post);
                } catch (\Exception $e) {
                    trigger_error("Unable to delete post: {$e->getMessage()}");
                }
            }

            $posts = $repository->fetchAll();

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            if ($request->getHeaderLine('HX-Request')) {
                return $twig->render($response, 'admin/posts/_table.html.twig', [
                    'posts' => $posts,
                    'status_labels' => PostStatus::labels(),
                ]);
            }

            return $twig->render($response, 'admin/posts/index.html.twig', [
                'posts' => $posts,
                'statuses' => PostStatus::labels(),
            ]);
        })->add(new RequireTwigMiddleware($container));

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var PostRepository $repository */
            $repository = $container->get(PostRepository::class);
            $posts = $repository->fetchAll();

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            return $twig->render($response, 'admin/posts/index.html.twig', [
                'posts' => $posts,
                'statuses' => PostStatus::labels(),
            ]);
        })->add(new RequireTwigMiddleware($container));
    })->add($app->getContainer()->get(AuthMiddleware::class));
}
