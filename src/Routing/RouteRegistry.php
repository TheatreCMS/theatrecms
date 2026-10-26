<?php

namespace TheatreCMS\Routing;

use RuntimeException;
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
     * @return RouteGroup
     */
    private function adminRoutesForContentType(ContentTypeDefinition $definition): array
    {
        return [
            'prefix' => '/' . $definition->adminSlug(),
            'routes' => [
                ['get', '/create', 'create'],
                ['post', '/create', 'store'],
                ['post', '/edit', 'update'],
                ['get', '/edit/{id}', 'edit'],
                ['delete', '/{id}', 'destroy'],
                ['get', '/{id}', 'show'],
                ['get', '', 'index'],
            ],
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
