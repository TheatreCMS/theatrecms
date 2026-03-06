<?php

use Clubdeuce\TheatreCMS\Controllers\ProductionController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Clubdeuce\TheatreCMS\Repositories\WorkRepository;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * The productions route group
 *
 * @var ContainerInterface $container
 */

if (isset($app)) {
    $app->group('/admin/productions', function ($group) {
        $container = $group->getContainer();

        $group->post('/create', [ProductionController::class, 'store']);
        $group->post('/edit', [ProductionController::class, 'update']);

        $group->get('/create', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $seasonRepo = $container->get(SeasonRepository::class);
            $personRepo = $container->get(PersonRepository::class);
            $worksRepo  = $container->get(WorkRepository::class);

            $vars = [
                'seasons' => $seasonRepo->fetchAll(),
                'people'  => $personRepo->fetchAll(),
                'works'   => $worksRepo->fetchAll(),
            ];


            return $twig->render($response, 'admin/productions/create.html.twig', $vars);
        });

        $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {

            /** @var ProductionRepository $productionRepo */
            $productionRepo = $container->get(ProductionRepository::class);
            $seasonRepo     = $container->get(SeasonRepository::class);
            $personRepo     = $container->get(PersonRepository::class);
            $worksRepo      = $container->get(WorkRepository::class);
            /** @var Production $production */
            $production     = $productionRepo->fetch($request->getAttribute('id'));

            $vars = [
                'production' => $production,
                'seasons'    => $seasonRepo->fetchAll(),
                'people'     => $personRepo->fetchAll(),
                'works'      => $worksRepo->fetchAll(),
                'creatives'  => $production->getCreativeTeam()->toArray(),
                'performers' => $production->getPerformers()->toArray(),
            ];

            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            return $twig->render($response, 'admin/productions/edit.html.twig', $vars);
        });

        $group->delete('/{id}', function (Request $request, Response $response) use ($container) {
            /** @var ProductionRepository $repository */
            $repository = $container->get(ProductionRepository::class);
            $production = $repository->fetch($request->getAttribute('id'));

            $repository->delete($production);

            $data = [
                'productions' => $repository->fetchAll(),
            ];

            if ($request->getHeaderLine('HX-Request'))
                // fetch the twig instance and render the partial for HTMX request
                return $container->get(Twig::class)->render($response, 'admin/productions/_table.html.twig', $data);

            // this is not an HTMX request, so return to the listing page
            return $response->withHeader('Location', '/admin/productions');
        });

        $group->get('', function (Request $request, Response $response) use ($container) {
            /** @var Twig $twig */
            $twig = $container->get(Twig::class);

            $prodRepo = $container->get(ProductionRepository::class);
            $productions = $prodRepo->fetchAll();

            return $twig->render($response, 'admin/productions/index.html.twig', ['productions' => $productions]);
        });
    })->add(new RequireTwigMiddleware($container))->add($container->get(AuthMiddleware::class));
}
