<?php
define('ROOT_DIR', dirname(__DIR__));
const APP_DIR = ROOT_DIR . '/app';
const ROUTES_DIR = APP_DIR . '/routes';

require_once ROOT_DIR . "/vendor/autoload.php";

use Clubdeuce\TheatreCMS\Controllers\LoginController;
use Clubdeuce\TheatreCMS\Middleware\RequireTwigMiddleware;
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
require ROUTES_DIR . '/seasons.php';
require ROUTES_DIR . '/admin/events.php';
require ROUTES_DIR . '/admin/people.php';
require ROUTES_DIR . '/admin/productions.php';
require ROUTES_DIR . '/admin/sponsors.php';
require ROUTES_DIR . '/admin/users.php';
require ROUTES_DIR . '/admin/venues.php';
require ROUTES_DIR . '/admin/works.php';

$app->get('/admin/login', [LoginController::class, 'login']);
$app->post('/admin/login', [LoginController::class, 'authenticate']);
$app->get('/admin/logout', [LoginController::class, 'logout']);
$app->post('/admin/register', [LoginController::class, 'register']);

$app->get('/admin', function (Request $request, Response $response) use ($container) {
    $twig = $container->get(Twig::class);

    return $twig->render($response, 'admin/index.html.twig');
})->add(new RequireTwigMiddleware($container));


$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("hello world");
    return $response;
});
$app->run();
