<?php
define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . "/vendor/autoload.php";

use Clubdeuce\TheatreCMS\Controllers\LoginController;
use Clubdeuce\TheatreCMS\Controllers\ProductionController;
use Clubdeuce\TheatreCMS\Middleware\AuthMiddleware;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\PersonRepository;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Clubdeuce\TheatreCMS\Repositories\ProductionRepository;
use Clubdeuce\TheatreCMS\Repositories\WorkRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;

/**
 * @var Psr\Container\ContainerInterface $container
 */
$container = require ROOT_DIR . '/app/bootstrap.php';
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

// Load external route files
require ROOT_DIR . '/app/routes/seasons.php';

$app->get('/admin/login', [LoginController::class, 'login']);
$app->post('/admin/login', [LoginController::class, 'authenticate']);
$app->get('/admin/logout', [LoginController::class, 'logout']);

$app->get('/admin', function (Request $request, Response $response) use ($container) {
    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'admin/index.html.twig');
})->add(new RequireTwigMiddleware($container));

$app->group('/admin/productions', function ($group) use ($container) {
    $group->post('/create', [ProductionController::class, 'store']);

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
    })->add(new RequireTwigMiddleware($container));

    $group->get('/delete/{id}', function (Request $request, Response $response) use ($container) {
        $repository = $container->get(ProductionRepository::class);
        /** @var ProductionRepository $repository */
        $repository->delete($repository->fetch($request->getAttribute('id')));

        return $response->withHeader('Location', '/admin/productions');
    });

    $group->get('/edit/{id}', function (Request $request, Response $response) use ($container) {

        $productionRepo = $container->get(ProductionRepository::class);
        $seasonRepo     = $container->get(SeasonRepository::class);
        $personRepo     = $container->get(PersonRepository::class);
        $worksRepo      = $container->get(WorkRepository::class);

        $vars = [
            'production' => $productionRepo->fetch($request->getAttribute('id')),
            'seasons'    => $seasonRepo->fetchAll(),
            'people'     => $personRepo->fetchAll(),
            'works'      => $worksRepo->fetchAll(),
        ];

        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        return $twig->render($response, 'admin/productions/edit.html.twig', $vars);
    })->add(new RequireTwigMiddleware($container));

    $group->get('', function (Request $request, Response $response) use ($container) {
        /** @var Twig $twig */
        $twig = $container->get(Twig::class);

        $prodRepo = $container->get(ProductionRepository::class);
        $productions = $prodRepo->fetchAll();

        return $twig->render($response, 'admin/productions/index.html.twig', ['productions' => $productions]);
    })->add(new RequireTwigMiddleware($container));
})->add(new AuthMiddleware());

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("hello world");
    return $response;
});
$app->run();
