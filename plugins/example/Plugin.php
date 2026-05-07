<?php

declare(strict_types=1);

namespace TheatreCMS\Plugin\Example;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use TheatreCMS\Plugin\AbstractPlugin;

/**
 * Example plugin demonstrating the three TheatreCMS extension points:
 *   1. DI service registration (register phase)
 *   2. Route registration (boot phase)
 *   3. Hook callbacks — filters and actions (boot phase)
 */
class Plugin extends AbstractPlugin
{
    public function getSlug(): string
    {
        return 'example';
    }

    /**
     * Phase 1 — register a greeting service with the DI container.
     */
    public function register(Container $container): void
    {
        $container->set(GreetingService::class, static fn() => new GreetingService('TheatreCMS'));
    }

    /**
     * Phase 2 — add a public route, a filter, and an action.
     */
    public function boot(App $app): void
    {
        $container = $app->getContainer();

        $app->get('/example/hello', function (Request $request, Response $response) use ($container): Response {
            /** @var GreetingService $greeter */
            $greeter = $container->get(GreetingService::class);
            $response->getBody()->write($greeter->greet());
            return $response->withHeader('Content-Type', 'text/plain');
        });

        // Filter example: append a note to any page_title value passed through apply_filters()
        add_filter('page_title', fn(string $title): string => $title . ' — Powered by TheatreCMS');

        // Action example: runs whenever do_action('season_saved', $season) is called in core.
        // do_action() passes args directly, so $season is the first argument.
        add_action('season_saved', function (mixed $season): void {
            // Plugins would do real work here (send notifications, invalidate caches, etc.)
            error_log('[ExamplePlugin] season_saved action fired');
        });
    }
}

// ---------------------------------------------------------------------------
// Supporting class — kept in the same file for simplicity.
// Larger plugins would split this into separate files and require_once them
// after the namespace declaration (namespace must come first in PHP).
// ---------------------------------------------------------------------------

class GreetingService
{
    public function __construct(private readonly string $name)
    {
    }

    public function greet(): string
    {
        return "Hello from {$this->name}!";
    }
}
