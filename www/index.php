<?php
require_once dirname(__DIR__) . "/vendor/autoload.php";

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
    if (!$container->has(Twig::class)) {
        $response->getBody()->write('Twig not available');
        return $response->withStatus(500);
    }

    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'layouts/admin.html.twig');
});

// Simple Twig test route. Renders templates/test.twig using the Twig service from the container.
$app->get('/twig-test', function (Request $request, Response $response) use ($container) {
    if (!$container->has(Twig::class)) {
        $response->getBody()->write('Twig not available');
        return $response->withStatus(500);
    }

    /** @var Twig $twig */
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'test.twig', ['name' => 'Daryl']);
});

$app->run();
