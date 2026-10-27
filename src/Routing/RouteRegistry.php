<?php

namespace TheatreCMS\Routing;

use RuntimeException;
use Slim\Interfaces\RouteInterface;
use TheatreCMS\ContentType\ContentTypeDefinition;

/**
 * Collects the admin and public routes for each content type, keyed by type name. Each
 * route is `[method, path, action]`, where `action` is a method on the type's controller;
 * `app/routes.php` turns them into Slim route groups.
 *
 * @phpstan-type RouteList list<array{0: string, 1: string, 2: string}>
 * @phpstan-type RouteGroup array{prefix: string, routes: RouteList}
 * @phpstan-type TypeRoutes array{definition: ContentTypeDefinition, admin: RouteGroup, public: RouteGroup|null}
 */
class RouteRegistry
{
    private static ?RouteRegistry $instance = null;

    /** @var array<string, TypeRoutes> */
    private array $routes = [];

    public static function setInstance(RouteRegistry $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): RouteRegistry
    {
        if (self::$instance === null) {
            throw new RuntimeException('The Route Registry has not been initialized.');
        }

        return self::$instance;
    }

    public function registerRoutesForTaxonomy($definition)
    {
        $this->registerAdminRoutesForTaxonomy($definition);
        $this->registerPublicRoutesForTaxonomy($definition);
    }

    private function registerAdminRoutesForTaxonomy($definition)
    {
        // Implement admin routes for taxonomy here
    }

    private function registerPublicRoutesForTaxonomy($definition)
    {
        // Implement public routes for taxonomy here
    }

    public function registerRoutesForContentType(ContentTypeDefinition $definition)
    {
        $this->routes[$definition->name] = [
            'definition' => $definition,
            'admin'      => $this->adminRoutesForContentType($definition),
            'public'     => $this->publicRoutesForContentType($definition),
        ];
    }

    public function prepend(string $key, array $routes) {

    }

    public function append(string $key, array $routes) {}

    /**
     * @return array<string, TypeRoutes>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * The route name for one of a content type's routes, e.g. `content_type.season.admin.index`.
     * assertNoCollisions() relies on it to tell a type's own routes from everyone else's.
     *
     * @param string $scope 'admin' or 'public'
     */
    public static function routeName(string $type, string $scope, string $action): string
    {
        return "content_type.{$type}.{$scope}.{$action}";
    }

    /**
     * Throws if a route a content type doesn't own is served at or under one of its prefixes,
     * e.g. a plugin's `/admin/seasons/report` against the season type's `/admin/seasons`.
     * Call once every route is registered: core, plugins and catch-alls.
     *
     * @param iterable<RouteInterface> $routes
     * @throws RuntimeException naming the first colliding route
     */
    public function assertNoCollisions(iterable $routes): void
    {
        foreach ($routes as $route) {
            $pattern = $route->getPattern();

            foreach ($this->routes as $type => $typeRoutes) {
                foreach ([$typeRoutes['admin'], $typeRoutes['public']] as $group) {
                    if ($group === null || !self::isAtOrUnder($pattern, $group['prefix'])) {
                        continue;
                    }

                    if (str_starts_with($route->getName() ?? '', "content_type.{$type}.")) {
                        continue;
                    }

                    throw new RuntimeException(sprintf(
                        'Route "%s %s" collides with content type "%s", which owns "%s".',
                        implode('|', $route->getMethods()),
                        $pattern,
                        $type,
                        $group['prefix']
                    ));
                }
            }
        }
    }

    /**
     * Whether `$pattern` is `$prefix` itself or a path below it (`/seasons`, `/seasons/{id}`,
     * `/seasons[/{page}]`), but not a sibling that merely starts with it (`/seasons-archive`).
     */
    private static function isAtOrUnder(string $pattern, string $prefix): bool
    {
        if (!str_starts_with($pattern, $prefix)) {
            return false;
        }

        return in_array(substr($pattern, strlen($prefix), 1), ['', '/', '['], true);
    }

    /**
     * @return RouteGroup
     */
    private function adminRoutesForContentType(ContentTypeDefinition $definition): array
    {
        $routes = [
            ['get', '/create', 'create'],
            ['post', '/create', 'store'],
            ['post', '/edit', 'update'],
            ['get', '/edit/{id}', 'edit'],
        ];

        if ($definition->supports('featured_image')) {
            $routes[] = ['delete', '/{id}/featured-image', 'removeFeaturedImage'];
        }

        array_push(
            $routes,
            ['delete', '/{id}', 'destroy'],
            ['get', '/{id}', 'show'],
            ['get', '', 'index'],
        );

        return [
            'prefix' => '/' . $definition->adminSlug(),
            'routes' => $routes,
        ];
    }

    /**
     * @return RouteGroup|null null when the type has no frontend routes
     */
    private function publicRoutesForContentType(ContentTypeDefinition $definition): ?array
    {
        if (!$definition->public) {
            return null;
        }

        $routes = [
            ['get', '/{id}', 'show'],
        ];

        if ($definition->hasArchive) {
            $routes[] = ['get', '', 'index'];
        }

        return [
            'prefix' => '/' . $definition->urlPrefix,
            'routes' => $routes,
        ];
    }
}
