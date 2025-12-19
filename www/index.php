<?php
require_once "../vendor/autoload.php";

use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Repositories\SeasonRepository;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

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

$app->group('/api', function (RouteCollectorProxy $group) {
    $group->group('/seasons', function (RouteCollectorProxy $group) {
        $group->post('', function (Request $request, Response $response) {
            /** @var SeasonRepository $seasonRepository */
            $seasonRepository = $this->get(SeasonRepository::class);

            $data = (array)$request->getParsedBody();

            if(empty($data)) {
                // there was no JSON body in the POST request
                $response->getBody()->write('{"error": "Empty JSON body."}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $season = new Season($data['slug'], $data['label']);
            $seasonRepository->create($season);

            $params = json_encode($season);

            if ($params)
                $response->getBody()->write($params);

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        });

        $group->get('', function (Request $request, Response $response) {
            /** @var SeasonRepository $seasonRepository */
            $seasonRepository = $this->get(SeasonRepository::class);
            $seasons = $seasonRepository->findAll();

            $data = json_encode($seasons);

            if ($data)
                $response->getBody()->write($data);

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        });

        $group->delete('/{id}', function (Request $request, Response $response, array $args) {
            $id = filter_var($args['id'], FILTER_VALIDATE_INT);

            if($id === false) {
                $response->getBody()->write('{"error": "Invalid season ID."}');
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            /** @var SeasonRepository $seasonRepository */
            $seasonRepository = $this->get(SeasonRepository::class);

            $seasonRepository->deleteById($id);

            $message = json_encode(['message' => "Season {$id} deleted."]);

            if ($message)
                $response->getBody()->write($message);

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        });

        $group->get('/{id}', function (Request $request, Response $response, array $args) {
            $id = filter_var($args['id'], FILTER_VALIDATE_INT);
            /** @var SeasonRepository $seasonRepository */
            $seasonRepository = $this->get(SeasonRepository::class);
            $season = $seasonRepository->findById($id);
            if($season === null) {
                $response->getBody()->write(json_encode(['error' => "Season {$id} not found."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode($season));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        });
    });

});

$app->run();
