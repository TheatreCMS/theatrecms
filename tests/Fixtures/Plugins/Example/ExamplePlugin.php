<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Example;

use DI\Container;
use Delight\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Plugin\AbstractPlugin;

/**
 * Fixture plugin exercising every extension point: a DI service, a hook, a route,
 * a capability, an admin menu item, and the declarative paths and commands.
 */
class ExamplePlugin extends AbstractPlugin
{
    public const CAPABILITY = 'manage_examples';

    public function register(Container $container): void
    {
        $container->set(ExampleGreeter::class, static fn(): ExampleGreeter => new ExampleGreeter());

        add_filter('example/greeting', static fn(string $greeting): string => $greeting . ' from the example plugin');
    }

    public function boot(App $app): void
    {
        $app->get('/example', function ($request, ResponseInterface $response) use ($app): ResponseInterface {
            $greeter = $app->getContainer()->get(ExampleGreeter::class);
            $response->getBody()->write(apply_filters('example/greeting', $greeter->greet()));

            return $response;
        });
    }

    public function entityPaths(): array
    {
        return [__DIR__ . '/Models'];
    }

    public function twigPaths(): array
    {
        return ['example' => __DIR__ . '/templates'];
    }

    public function migrationPaths(): array
    {
        return [__DIR__ . '/migrations'];
    }

    public function commands(): array
    {
        return [ExampleCommand::class];
    }

    public function capabilities(): array
    {
        return [Role::ADMIN => [self::CAPABILITY]];
    }

    public function adminMenuItems(): array
    {
        return [new AdminMenuItem('Examples', 'admin/examples', self::CAPABILITY)];
    }
}
