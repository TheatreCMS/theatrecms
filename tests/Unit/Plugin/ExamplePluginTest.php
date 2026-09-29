<?php

namespace TheatreCMS\Tests\Unit\Plugin;

use DI\Container;
use Delight\Auth\Role;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use TheatreCMS\Admin\AdminMenuRegistry;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\Plugin\ComposerPluginDiscovery;
use TheatreCMS\Plugin\PluginManager;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExampleGreeter;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExamplePlugin;

/**
 * End to end: the fixture plugin, found through its composer.json the way an installed
 * package is, registers a service, a hook, a route, a capability and an admin menu item.
 */
class ExamplePluginTest extends TestCase
{
    public function testFixturePluginExtendsTheApplication(): void
    {
        $container = new Container();
        $capabilities = new CapabilityRegistry();
        $capabilities->register(Role::ADMIN, ['manage_users']);
        $adminMenu = new AdminMenuRegistry();

        $manager = new PluginManager(new ComposerPluginDiscovery(static fn(): array => [
            'theatrecms/example-plugin' => __DIR__ . '/../../Fixtures/Plugins/Example',
        ]));
        $manager->registerAll($container, $capabilities, $adminMenu);

        $app = AppFactory::create(container: $container);
        $manager->bootAll($app);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/example');
        $response = $app->handle($request);

        $this->assertInstanceOf(ExampleGreeter::class, $container->get(ExampleGreeter::class));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello from the example plugin', (string) $response->getBody());
        $this->assertContains(ExamplePlugin::CAPABILITY, $capabilities->capabilitiesFor(Role::ADMIN));
        $this->assertContains('manage_users', $capabilities->capabilitiesFor(Role::ADMIN));
        $this->assertSame('Examples', $adminMenu->groups()[0]['items'][0]->label);
        $this->assertStringEndsWith('/Example/Models', $manager->entityPaths()[0]);
        $this->assertArrayHasKey('theatrecms/example-plugin', $manager->migrationPaths());
    }
}
