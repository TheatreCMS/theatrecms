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

        $group->post('/create', function (Request $request, Response $response) use ($container) {
            /** @var PageRepository $repository */
            $repository = $container->get(PageRepository::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $isHtmx = (bool) $request->getHeaderLine('HX-Request');
            $data = $request->getParsedBody();

            if (empty($data)) {
                if ($isHtmx) {
                    return $twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type' => 'error',
                        'message' => 'No data received.',
                    ]);
                }
                return $response->withStatus(400);
            }

            try {
                $repository->create([
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'] ?? null,
                ]);
            } catch (\InvalidArgumentException $e) {
                if ($isHtmx) {
                    return $twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type' => 'error',
                        'message' => $e->getMessage(),
                    ]);
                }
                $response->getBody()->write($e->getMessage());
                return $response->withStatus(400);
            }

            if ($isHtmx) {
                return $twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'success',
                    'message' => 'Page created successfully.',
                ]);
            }

            return $response->withHeader('Location', '/admin/pages');
        });

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/pages/create.html.twig');
        })->add(new RequireTwigMiddleware($container));

        $group->post('/edit', function (Request $request, Response $response) use ($container) {
            /** @var PageRepository $repository */
            $repository = $container->get(PageRepository::class);
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $isHtmx = (bool) $request->getHeaderLine('HX-Request');
            $data = $request->getParsedBody();

            if (empty($data)) {
                if ($isHtmx) {
                    return $twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type' => 'error',
                        'message' => 'No data received.',
                    ]);
                }
                return $response->withStatus(400);
            }

            $pageId = (int) ($data['pageId'] ?? 0);
            $title = $data['title'] ?? null;
            $content = $data['content'] ?? null;
            $slug = $data['slug'] ?? null;

            $page = $repository->fetch($pageId);

            if (!$page) {
                if ($isHtmx) {
                    return $twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type' => 'error',
                        'message' => 'Page not found.',
                    ]);
                }
                return $response->withStatus(404);
            }

            if (empty($title) || empty($content)) {
                if ($isHtmx) {
                    return $twig->render($response, 'admin/partials/_alert.html.twig', [
                        'type' => 'error',
                        'message' => 'Title and content are required.',
                    ]);
                }
                return $response->withStatus(400);
            }

            $page->setTitle($title);
            $page->setContent($content);
            $page->touchModified();

            if (!empty($slug)) {
                $repository->updateSlug($page, $slug);
            }

            $repository->update($page);

            if ($isHtmx) {
                return $twig->render($response, 'admin/partials/_alert.html.twig', [
                    'type' => 'success',
                    'message' => 'Page saved successfully.',
                ]);
            }

            return $response->withHeader('Location', '/admin/pages');
        });

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
