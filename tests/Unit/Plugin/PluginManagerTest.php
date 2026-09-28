<?php

namespace TheatreCMS\Tests\Unit\Plugin;

use DI\Container;
use Delight\Auth\Role;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use TheatreCMS\Admin\AdminMenuRegistry;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\Plugin\PluginDescriptor;
use TheatreCMS\Plugin\PluginDiscovery;
use TheatreCMS\Plugin\PluginManager;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExamplePlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\AlphaPlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\BetaPlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\CallLog;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\NotAPlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\RecordingLogger;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\ThrowsOnBootPlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\ThrowsOnDeclarePlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\ThrowsOnRegisterPlugin;

/**
 * @coversDefaultClass \TheatreCMS\Plugin\PluginManager
 */
class PluginManagerTest extends TestCase
{
    private RecordingLogger $logger;
    private CapabilityRegistry $capabilities;
    private AdminMenuRegistry $adminMenu;

    protected function setUp(): void
    {
        CallLog::$calls = [];
        $this->logger = new RecordingLogger();
        $this->capabilities = new CapabilityRegistry();
        $this->adminMenu = new AdminMenuRegistry();
    }

    public function testRegistersThenBootsPluginsInDiscoveryOrder(): void
    {
        $manager = $this->manager([
            'vendor/alpha' => AlphaPlugin::class,
            'vendor/beta' => BetaPlugin::class,
        ]);

        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);
        $manager->bootAll($this->app());

        $this->assertSame(['alpha:register', 'beta:register', 'alpha:boot', 'beta:boot'], CallLog::$calls);
        $this->assertSame(['vendor/alpha', 'vendor/beta'], array_keys($manager->registered()));
    }

    public function testCollectsDeclarationsFromEveryPlugin(): void
    {
        $manager = $this->manager([
            'vendor/alpha' => AlphaPlugin::class,
            'vendor/beta' => BetaPlugin::class,
        ]);
        $manager->discover();

        $this->assertSame(['/alpha/Models', '/beta/Models'], $manager->entityPaths());
        $this->assertSame(['shared' => ['/alpha/templates', '/beta/templates']], $manager->twigPaths());
        $this->assertSame(['vendor/alpha' => ['/alpha/migrations']], $manager->migrationPaths());
        $this->assertSame(['Alpha\\Command'], $manager->commands());
    }

    public function testGrantsCapabilitiesOnTopOfCoreAndAddsAdminMenuItems(): void
    {
        $this->capabilities->register(Role::ADMIN, ['manage_users']);
        $manager = $this->manager(['vendor/alpha' => AlphaPlugin::class]);

        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);

        $this->assertSame(['manage_users', 'manage_alpha'], $this->capabilities->capabilitiesFor(Role::ADMIN));
        $this->assertSame('Alpha', $this->adminMenu->groups()[0]['items'][0]->label);
    }

    public function testSkipsMissingClassesAndNonPluginsAndLogsThem(): void
    {
        $manager = $this->manager([
            'vendor/alpha' => AlphaPlugin::class,
            'vendor/missing' => 'Vendor\\Missing\\Plugin',
            'vendor/not-a-plugin' => NotAPlugin::class,
        ]);

        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);

        $this->assertSame(['vendor/alpha'], array_keys($manager->registered()));
        $this->assertSame(['vendor/missing', 'vendor/not-a-plugin'], $this->logger->failedPackages());
    }

    public function testPluginThatThrowsInRegisterIsSkippedWithoutItsCapabilitiesMenuOrBoot(): void
    {
        $manager = $this->manager([
            'vendor/alpha' => AlphaPlugin::class,
            'vendor/broken' => ThrowsOnRegisterPlugin::class,
            'vendor/beta' => BetaPlugin::class,
        ]);

        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);
        $manager->bootAll($this->app());

        $this->assertSame(['vendor/alpha', 'vendor/beta'], array_keys($manager->registered()));
        $this->assertSame(['alpha:register', 'beta:register', 'alpha:boot', 'beta:boot'], CallLog::$calls);
        $this->assertSame([], $this->capabilities->rolesFor('manage_broken'));
        $labels = array_map(static fn($item) => $item->label, $this->adminMenu->groups()[0]['items']);
        $this->assertNotContains('Broken', $labels);
        $this->assertSame(['vendor/broken'], $this->logger->failedPackages());
        $this->assertStringContainsString('register exploded', $this->logger->records[0]['context']['message']);
    }

    public function testPluginThatThrowsInBootDoesNotStopOthers(): void
    {
        $manager = $this->manager([
            'vendor/alpha' => AlphaPlugin::class,
            'vendor/boom' => ThrowsOnBootPlugin::class,
            'vendor/beta' => BetaPlugin::class,
        ]);

        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);
        $manager->bootAll($this->app());

        $this->assertSame(['alpha:register', 'beta:register', 'alpha:boot', 'beta:boot'], CallLog::$calls);
        $this->assertSame(['vendor/boom'], $this->logger->failedPackages());
    }

    public function testPluginThatThrowsWhileDeclaringIsNeverRegistered(): void
    {
        $manager = $this->manager([
            'vendor/declare' => ThrowsOnDeclarePlugin::class,
            'vendor/beta' => BetaPlugin::class,
        ]);

        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);

        $this->assertSame(['beta:register'], CallLog::$calls);
        $this->assertSame(['/beta/Models'], $manager->entityPaths());
        $this->assertSame(['vendor/declare'], $this->logger->failedPackages());
    }

    public function testDiscoverRunsOnlyOnce(): void
    {
        $discovery = $this->createMock(PluginDiscovery::class);
        $discovery->expects($this->once())->method('discover')->willReturn([]);
        $manager = new PluginManager($discovery, $this->logger);

        $manager->discover();
        $manager->registerAll(new Container(), $this->capabilities, $this->adminMenu);
    }

    public function testConfigureTwigAddsPluginNamespaces(): void
    {
        $manager = $this->manager(['theatrecms/example-plugin' => ExamplePlugin::class]);
        $manager->discover();
        $twig = Twig::create([]);

        $manager->configureTwig($twig);

        $this->assertSame("Hello from @example\n", $twig->getEnvironment()->render('@example/hello.html.twig'));
    }

    /**
     * @param array<string, string> $plugins package => class
     */
    private function manager(array $plugins): PluginManager
    {
        $descriptors = [];
        foreach ($plugins as $package => $class) {
            $descriptors[] = new PluginDescriptor($package, $class, '/' . $package);
        }

        $discovery = $this->createStub(PluginDiscovery::class);
        $discovery->method('discover')->willReturn($descriptors);

        return new PluginManager($discovery, $this->logger);
    }

    private function app(): App
    {
        return AppFactory::create(container: new Container());
    }
}
