<?php
require_once "../vendor/autoload.php";

use Clubdeuce\theatrecms\Models\Season;
use Clubdeuce\theatrecms\Repositories\SeasonRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

AppFactory::setContainer(require __DIR__ . '/../app/bootstrap.php');

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(phpinfo());
    return $response;
});

$app->group('/api', function (RouteCollectorProxy $group) use ($app) {
    $group->get('', function (Request $request, Response $response) use ($app) {
        $response->getBody()->write('API up and responding');
        return $response->withStatus(200);
    });

    $group->group('/seasons', function (RouteCollectorProxy $group) {
        $group->post('/', function (Request $request, Response $response) {
            /** @var SeasonRepository $seasonRepository */
            $seasonRepository = $this->get(SeasonRepository::class);

            $data = $request->getParsedBody();

            if($data === null) {
                $response->getBody()->write(json_encode(['error' => 'Empty JSON body.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $season = new Season($data['slug'], $data['label']);
            $seasonRepository->create($season);

            $response->getBody()->write(json_encode($season));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
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
