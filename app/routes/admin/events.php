<?php

use TheatreCMS\Controllers\EventController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Repositories\EventRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

if (isset($app)) {
    $app->group('/admin/events', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [EventController::class, 'store']);
        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $productions = $container->get(\TheatreCMS\Repositories\ProductionRepository::class)->fetchAll();
            $venues = $container->get(\TheatreCMS\Repositories\VenueRepository::class)->fetchAll();

            return $twig->render($response, 'admin/events/create.html.twig', ['productions' => $productions, 'venues' => $venues]);
        })->add(new RequireTwigMiddleware($container));

        $group->post('/edit', [EventController::class, 'update']);
        $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {
            $repository = $container->get(EventRepository::class);
            /** @var EventRepository $repository */
            $event = $repository->fetch($request->getAttribute('id'));

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);
            $productions = $container->get(\TheatreCMS\Repositories\ProductionRepository::class)->fetchAll();
            $venues = $container->get(\TheatreCMS\Repositories\VenueRepository::class)->fetchAll();

            return $twig->render($response, 'admin/events/edit.html.twig', ['event' => $event, 'productions' => $productions, 'venues' => $venues]);
        })->add(new RequireTwigMiddleware($container));

        $group->delete('/{id}', function (Request $request, Response $response) use ($container) {
            /** @var EventRepository $repository */
            $repository = $container->get(EventRepository::class);
            $event     = $repository->fetch($request->getAttribute('id'));

            try {
                if ($event) {
                    $repository->delete($event);
                }
            } catch (Exception $e) {
                trigger_error("Unable to delete event: {$e->getMessage()}");
            }

            $data = [
                'events'  => $repository->fetchAll()
            ];

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            if ($request->getHeaderLine('HX-Request'))
                return $twig->render($response, 'admin/events/_table.html.twig', $data);

            return $twig->render($response, 'admin/events/index.html.twig', $data);

        })->add(new RequireTwigMiddleware($container));

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            /** @var EventController $eventController */
            $eventController = $container->get(EventController::class);
            $events = $eventController->repository()->fetchAll();

            return $twig->render($response, 'admin/events/index.html.twig', ['events' => $events]);
        })->add(new RequireTwigMiddleware($container));
    })->add($app->getContainer()->get(AuthMiddleware::class));
}
