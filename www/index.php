<?php
require_once dirname(__DIR__) . "/vendor/autoload.php";

use Clubdeuce\TheatreCMS\Controllers\SeasonController;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;

/**
 * @var Psr\Container\ContainerInterface $container
 */
$container = require __DIR__ . '/../app/bootstrap.php';
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("hello world");
    return $response;
});

$app->get('/admin', function (Request $request, Response $response) use ($container) {
    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'layouts/admin.html.twig');
})->add(new RequireTwigMiddleware($container));

$app->get('/admin/seasons/create', function (Request $request, Response $response) use ($container) {
    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'admin/seasons/create.html.twig');
})->add(new RequireTwigMiddleware($container));

$app->get('/admin/seasons/edit/{id}', function (Request $request, Response $response) use ($container) {
    $repository = $container->get(SeasonRepository::class);
    /** @var SeasonRepository $repository */
    $season = $repository->fetch($request->getAttribute('id'));

    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'admin/seasons/edit.html.twig', ['season' => $season]);
})->add(new RequireTwigMiddleware($container));

$app->get('/admin/seasons', function (Request $request, Response $response) use ($container) {
    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    /** @var SeasonController $seasonController */
    $seasonController = $container->get(SeasonController::class);
    $seasons = $seasonController->repository()->fetchAll();

    return $twig->render($response, 'admin/seasons/index.html.twig', ['seasons' => $seasons]);
})->add(new RequireTwigMiddleware($container));

$app->post('/admin/seasons/edit', [SeasonController::class, 'update']);
$app->post('/admin/seasons', [SeasonController::class, 'store']);
$app->run();
