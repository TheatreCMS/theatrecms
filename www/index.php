<?php
require_once "../vendor/autoload.php";
require_once "../app/bootstrap.php";

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;


AppFactory::setContainer(require __DIR__ . '/../app/bootstrap.php');

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("Hello, World!");
    return $response;
});

$app->run();
