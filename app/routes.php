<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\Capability;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Controllers\LinkPreviewController;
use TheatreCMS\Controllers\MediaController;
use TheatreCMS\Controllers\MenuController;
use TheatreCMS\Controllers\ProfileController;
use TheatreCMS\Controllers\PublicContentController;
use TheatreCMS\Controllers\SettingsController;
use TheatreCMS\Controllers\TermController;
use TheatreCMS\Controllers\ThemesController;
use TheatreCMS\Controllers\UsersController;
use TheatreCMS\Middleware\AuthMiddleware;
use TheatreCMS\Middleware\RequireCapabilityMiddleware;
use TheatreCMS\Middleware\RequireTwigMiddleware;
use TheatreCMS\Routing\RouteRegistry;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

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
 * types sharing a controller class (e.g. EntryController) each get the right one. The
 * definition is also attached to the request for controllers that aren't built with it
 * (see RendersPublicContent). Not static: Slim binds route closures to the container.
 */
$contentTypeHandler = function (ContentTypeDefinition $definition, string $action) use ($container): Closure {
    return function (Request $request, Response $response, array $args) use ($container, $definition, $action): Response {
        $controller = $container->make($definition->controller, ['definition' => $definition]);
        $request = $request->withAttribute(ContentTypeDefinition::class, $definition);

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

// The root type's routes (empty prefix, e.g. pages at `/{path}`) match almost any URL, so
// www/index.php registers them last, through $registerRootContentRoutes.
$rootContentRoutes = [];

foreach ($routeRegistry->all() as ['definition' => $definition, 'admin' => $admin, 'public' => $public]) {
    $app->group($admin['prefix'], function (RouteCollectorProxyInterface $group) use ($addRoutes, $definition, $admin) {
        $addRoutes($group, $definition, 'admin', $admin['routes']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), $definition->capability))
      ->add($container->get(AuthMiddleware::class));

    if ($public !== null) {
        if (!is_subclass_of($definition->controller, PublicContentController::class)) {
            throw new RuntimeException(sprintf(
                'Content type "%s" is public, but its controller %s does not implement %s.',
                $definition->name,
                $definition->controller,
                PublicContentController::class
            ));
        }

        if ($public['prefix'] === '') {
            $rootContentRoutes[] = [$definition, $public];
            continue;
        }

        $app->group($public['prefix'], function (RouteCollectorProxyInterface $group) use ($addRoutes, $definition, $public) {
            $addRoutes($group, $definition, 'public', $public['routes']);
        })->add(new RequireTwigMiddleware($container));
    }
}

$registerRootContentRoutes = function () use ($app, $container, $addRoutes, $rootContentRoutes): void {
    foreach ($rootContentRoutes as [$definition, $public]) {
        $app->group('', function (RouteCollectorProxyInterface $group) use ($addRoutes, $definition, $public) {
            $addRoutes($group, $definition, 'public', $public['routes']);
        })->add(new RequireTwigMiddleware($container));
    }
};

foreach (TaxonomyRegistry::getInstance()->all() as $taxonomy) {
    $routeRegistry->registerRoutesForTaxonomy($taxonomy);
}

/**
 * Adds a taxonomy's routes, all handled by TermController with the taxonomy's name as the
 * `taxonomy` route argument. Named like content-type routes, for the collision check.
 */
$addTaxonomyRoutes = function (RouteCollectorProxyInterface $group, TaxonomyDefinition $taxonomy, string $scope, array $routes) use ($container): void {
    foreach ($routes as [$method, $path, $action]) {
        $handler = function (Request $request, Response $response, array $args) use ($container, $taxonomy, $action): Response {
            return $container->get(TermController::class)->{$action}($request, $response, ['taxonomy' => $taxonomy->name] + $args);
        };

        $group->{$method}($path, $handler)
            ->setName(RouteRegistry::taxonomyRouteName($taxonomy->name, $scope, $action));
    }
};

foreach ($routeRegistry->allTaxonomies() as ['definition' => $taxonomy, 'admin' => $admin, 'public' => $public]) {
    $app->group($admin['prefix'], function (RouteCollectorProxyInterface $group) use ($addTaxonomyRoutes, $taxonomy, $admin) {
        $addTaxonomyRoutes($group, $taxonomy, 'admin', $admin['routes']);
    })->add(new RequireTwigMiddleware($container))
      ->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), $taxonomy->capability))
      ->add($container->get(AuthMiddleware::class));

    if ($public !== null) {
        $app->group($public['prefix'], function (RouteCollectorProxyInterface $group) use ($addTaxonomyRoutes, $taxonomy, $public) {
            $addTaxonomyRoutes($group, $taxonomy, 'public', $public['routes']);
        })->add(new RequireTwigMiddleware($container));
    }
}

/**
 * Admin screens that aren't a content type or taxonomy, so the registries above don't build
 * them: prefix => [controller, capability (null: any logged-in user), routes]. Within a group,
 * list a static path before any variable path matching it (`/picker` before `/{id}`): FastRoute
 * refuses the other order.
 *
 * @var array<string, array{0: class-string, 1: string|null, 2: list<array{0: string, 1: string, 2: string}>}> $adminScreens
 */
$adminScreens = [
    '/admin/menus' => [MenuController::class, Capability::MANAGE_MENUS, [
        ['get', '', 'index'],
        ['get', '/create', 'create'],
        ['post', '/create', 'store'],
        ['get', '/edit/{id}', 'edit'],
        ['post', '/{id}/items', 'saveTree'],
        ['delete', '/{id}', 'destroy'],
    ]],
    // Browse, upload and delete alike need UPLOAD_FILES: a group takes one capability.
    '/admin/media' => [MediaController::class, Capability::UPLOAD_FILES, [
        ['get', '', 'index'],
        ['get', '/picker', 'picker'],
        ['post', '/library-upload', 'upload'],
        ['get', '/{id}/select', 'select'],
        ['get', '/{id}', 'show'],
        ['patch', '/{id}', 'update'],
        ['delete', '/{id}', 'destroy'],
    ]],
    // Frozen: the EditorJS image, gallery and carousel tools depend on this upload endpoint's
    // JSON contract. Everything else about media lives under /admin/media.
    '/admin/images' => [ImageUploadController::class, Capability::UPLOAD_FILES, [
        ['post', '/upload', 'upload'],
    ]],
    '/admin/link-preview' => [LinkPreviewController::class, Capability::UPLOAD_FILES, [
        ['get', '/fetch', 'fetch'],
    ]],
    '/admin/settings' => [SettingsController::class, Capability::MANAGE_OPTIONS, [
        ['get', '', 'index'],
        ['post', '', 'update'],
    ]],
    '/admin/themes' => [ThemesController::class, Capability::SWITCH_THEMES, [
        ['get', '', 'index'],
        ['post', '/activate', 'activate'],
    ]],
    '/admin/users' => [UsersController::class, Capability::MANAGE_USERS, [
        ['get', '', 'index'],
        ['get', '/create', 'create'],
        ['post', '/create', 'store'],
        ['get', '/edit/{id}', 'edit'],
        ['post', '/edit', 'update'],
        ['delete', '/{id}', 'destroy'],
    ]],
    '/admin/profile' => [ProfileController::class, null, [
        ['get', '', 'edit'],
        ['post', '', 'update'],
    ]],
];

foreach ($adminScreens as $prefix => [$controller, $capability, $routes]) {
    $routeGroup = $app->group($prefix, function (RouteCollectorProxyInterface $group) use ($controller, $routes) {
        foreach ($routes as [$method, $path, $action]) {
            $group->{$method}($path, [$controller, $action]);
        }
    })->add(new RequireTwigMiddleware($container));

    if ($capability !== null) {
        $routeGroup->add(new RequireCapabilityMiddleware($container->get(AuthorizationService::class), $capability));
    }

    $routeGroup->add($container->get(AuthMiddleware::class));
}
