<?php

namespace TheatreCMS\Routing;

use RuntimeException;
use Slim\Interfaces\RouteInterface;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Controllers\PublicContentController;
use TheatreCMS\Taxonomy\TaxonomyDefinition;

/**
 * Collects the admin and public routes for each content type and each taxonomy, keyed by
 * name. Each route is `[method, path, action]`, where `action` is a method on the type's
 * controller (TermController for taxonomies); `app/routes.php` turns them into Slim route
 * groups.
 *
 * @phpstan-type RouteList list<array{0: string, 1: string, 2: string}>
 * @phpstan-type RouteGroup array{prefix: string, routes: RouteList}
 * @phpstan-type TypeRoutes array{definition: ContentTypeDefinition, admin: RouteGroup, public: RouteGroup|null}
 * @phpstan-type TaxonomyRoutes array{definition: TaxonomyDefinition, admin: RouteGroup, public: RouteGroup|null}
 */
class RouteRegistry
{
    private static ?RouteRegistry $instance = null;

    /** @var array<string, TypeRoutes> */
    private array $routes = [];

    /** @var array<string, TaxonomyRoutes> */
    private array $taxonomyRoutes = [];

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

    public function registerRoutesForTaxonomy(TaxonomyDefinition $definition): void
    {
        $this->taxonomyRoutes[$definition->name] = [
            'definition' => $definition,
            'admin'      => $this->adminRoutesForTaxonomy($definition),
            'public'     => $this->publicRoutesForTaxonomy($definition),
        ];
    }

    public function registerRoutesForContentType(ContentTypeDefinition $definition)
    {
        $this->routes[$definition->name] = [
            'definition' => $definition,
            'admin'      => $this->adminRoutesForContentType($definition),
            'public'     => $this->publicRoutesForContentType($definition),
        ];
    }

    public function prepend(string $key, array $routes)
    {
    }

    public function append(string $key, array $routes)
    {
    }

    /**
     * @return array<string, TypeRoutes>
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * @return array<string, TaxonomyRoutes>
     */
    public function allTaxonomies(): array
    {
        return $this->taxonomyRoutes;
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
     * The route name for one of a taxonomy's routes, e.g. `taxonomy.genre.public.archive`.
     *
     * @param string $scope 'admin' or 'public'
     */
    public static function taxonomyRouteName(string $taxonomy, string $scope, string $action): string
    {
        return "taxonomy.{$taxonomy}.{$scope}.{$action}";
    }

    /**
     * Throws if a route a content type or taxonomy doesn't own is served at or under one of its
     * prefixes, e.g. a plugin's `/admin/seasons/report` against the season type's
     * `/admin/seasons`. Call once every route is registered: core, plugins and catch-alls.
     *
     * @param iterable<RouteInterface> $routes
     * @throws RuntimeException naming the first colliding route
     */
    public function assertNoCollisions(iterable $routes): void
    {
        $owners = $this->prefixOwners();

        foreach ($routes as $route) {
            $pattern = $route->getPattern();

            foreach ($owners as [$prefix, $namePrefix, $owner]) {
                if (!self::isAtOrUnder($pattern, $prefix) || str_starts_with($route->getName() ?? '', $namePrefix)) {
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Route "%s %s" collides with %s, which owns "%s".',
                    implode('|', $route->getMethods()),
                    $pattern,
                    $owner,
                    $prefix
                ));
            }
        }
    }

    /**
     * Every registered prefix, with the route-name prefix its owner's routes carry.
     *
     * @return list<array{0: string, 1: string, 2: string}> prefix, route-name prefix, owner description
     */
    private function prefixOwners(): array
    {
        $owners = [];

        foreach ($this->routes as $type => $typeRoutes) {
            foreach ([$typeRoutes['admin'], $typeRoutes['public']] as $group) {
                if ($group !== null) {
                    $owners[] = [$group['prefix'], "content_type.{$type}.", "content type \"{$type}\""];
                }
            }
        }

        foreach ($this->taxonomyRoutes as $taxonomy => $taxonomyRoutes) {
            foreach ([$taxonomyRoutes['admin'], $taxonomyRoutes['public']] as $group) {
                if ($group !== null) {
                    $owners[] = [$group['prefix'], "taxonomy.{$taxonomy}.", "taxonomy \"{$taxonomy}\""];
                }
            }
        }

        return $owners;
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

        // A controller that isn't a PublicContentController is rejected in app/routes.php.
        $routes = is_subclass_of($definition->controller, PublicContentController::class)
            ? $definition->controller::additionalPublicRoutes()
            : [];
        $routes[] = ['get', '/{slug}', 'single'];

        if ($definition->hasArchive) {
            $routes[] = ['get', '', 'archive'];
        }

        return [
            'prefix' => '/' . $definition->urlPrefix,
            'routes' => $routes,
        ];
    }

    /**
     * Term management at `/admin/taxonomies/{name}`, the URL the admin templates link to.
     *
     * @return RouteGroup
     */
    private function adminRoutesForTaxonomy(TaxonomyDefinition $definition): array
    {
        return [
            'prefix' => '/admin/taxonomies/' . $definition->name,
            'routes' => [
                ['post', '', 'store'],
                ['get', '/edit/{id}', 'edit'],
                ['post', '/edit/{id}', 'update'],
                ['delete', '/{id}', 'destroy'],
                ['get', '', 'index'],
            ],
        ];
    }

    /**
     * Term archives at `/{urlPrefix}/{slug}`. There's no page at `/{urlPrefix}` itself.
     *
     * @return RouteGroup|null null when the taxonomy has no archive
     */
    private function publicRoutesForTaxonomy(TaxonomyDefinition $definition): ?array
    {
        if (!$definition->hasArchive) {
            return null;
        }

        return [
            'prefix' => '/' . $definition->urlPrefix,
            'routes' => [
                ['get', '/{slug}', 'archive'],
            ],
        ];
    }
}
