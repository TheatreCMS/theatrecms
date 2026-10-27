<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Routing\RouteRegistry;

/**
 * Register all routes for the application
 *
 * @var \Slim\App $app
 * @var \DI\Container $container
 */
$routeRegistry   = RouteRegistry::getInstance();
$contentRegistry = ContentTypeRegistry::getInstance();

foreach ($contentRegistry->all() as $definition) {
    $routeRegistry->registerRoutesForContentType($definition);
}

/**
 * Builds a route handler that creates the type's controller with its own definition, so
 * types sharing a controller class (e.g. EntryController) each get the right one. Not
 * static: Slim binds route closures to the container.
 */
$contentTypeHandler = function (ContentTypeDefinition $definition, string $action) use ($container): Closure {
    return function (Request $request, Response $response, array $args) use ($container, $definition, $action): Response {
        $controller = $container->make($definition->controller, ['definition' => $definition]);

        return $controller->{$action}($request, $response, $args);
    };
};

// Named so RouteRegistry::assertNoCollisions() can tell the type's own routes from others.
$addRoutes = function (RouteCollectorProxyInterface $group, ContentTypeDefinition $definition, string $scope, array $routes) use ($contentTypeHandler): void {
    foreach ($routes as [$method, $path, $action]) {
        $group->{$method}($path, $contentTypeHandler($definition, $action))
            ->setName(RouteRegistry::routeName($definition->name, $scope, $action));
    }
};

foreach ($routeRegistry->all() as ['definition' => $definition, 'admin' => $admin, 'public' => $public]) {
    $app->group($admin['prefix'], function (RouteCollectorProxyInterface $group) use ($addRoutes, $definition, $admin) {
        $addRoutes($group, $definition, 'admin', $admin['routes']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), $definition->capability))
      ->add($container->get(AuthMiddleware::class));

    if ($public !== null) {
        $app->group($public['prefix'], function (RouteCollectorProxyInterface $group) use ($addRoutes, $definition, $public) {
            $addRoutes($group, $definition, 'public', $public['routes']);
        })->add(new RequireTwigMiddleware($container));
    }
}

// foreach ($taxonomyRegistry->all() as $definition) {
//     $routeRegistry->registerRoutesForTaxonomy($definition);
// }