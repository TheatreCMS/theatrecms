<?php

namespace TheatreCMS\Tests\Unit\Console;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Tester\ApplicationTester;
use TheatreCMS\Console\ConsoleApplicationFactory;
use TheatreCMS\Plugin\PluginManager;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExampleCommand;
use TheatreCMS\Tests\Fixtures\Plugins\Example\ExampleGreeter;
use TheatreCMS\Tests\Fixtures\Plugins\Example\HiddenExampleCommand;
use TheatreCMS\Tests\Fixtures\Plugins\Example\UnattributedCommand;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\NotAPlugin;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\RecordingLogger;

class ConsoleApplicationFactoryTest extends TestCase
{
    public function testListsCoreAndPluginCommandsWithoutBuildingThem(): void
    {
        $container = $this->createMock(Container::class);
        $container->expects($this->never())->method('get');

        $application = $this->factory($container, [ExampleCommand::class])->create();

        foreach (ConsoleApplicationFactory::CORE_COMMANDS as $class) {
            $name = explode('|', (new \ReflectionClass($class))->getAttributes()[0]->getArguments()['name'])[0];
            $this->assertTrue($application->has($name), $name);
        }
        $this->assertInstanceOf(LazyCommand::class, $application->get('example:greet'));
        $this->assertSame('Print the example greeting', $application->get('example:greet')->getDescription());
        $this->assertSame(['example:hello'], $application->get('example:greet')->getAliases());
    }

    public function testRunsAPluginCommandBuiltThroughTheContainer(): void
    {
        $container = new Container();
        $container->set(ExampleGreeter::class, new ExampleGreeter());
        $application = $this->factory($container, [ExampleCommand::class])->create();
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'example:hello']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame("Hello\n", $tester->getDisplay(true));
    }

    public function testALeadingPipeHidesTheCommand(): void
    {
        $application = $this->factory(new Container(), [HiddenExampleCommand::class])->create();

        $this->assertTrue($application->get('example:hidden')->isHidden());
    }

    public function testSkipsAndLogsInvalidPluginCommands(): void
    {
        $logger = new RecordingLogger();
        $application = $this->factory(
            new Container(),
            [UnattributedCommand::class, NotAPlugin::class, 'Missing\\Command', ExampleCommand::class],
            $logger,
        )->create();

        $this->assertTrue($application->has('example:greet'));
        $this->assertSame(
            [UnattributedCommand::class, NotAPlugin::class, 'Missing\\Command'],
            array_map(static fn(array $record): string => $record['context']['class'], $logger->records),
        );
        $this->assertStringContainsString('#[AsCommand]', $logger->records[0]['context']['message']);
    }

    /**
     * @param string[] $pluginCommands
     */
    private function factory(Container $container, array $pluginCommands, ?RecordingLogger $logger = null): ConsoleApplicationFactory
    {
        $plugins = $this->createStub(PluginManager::class);
        $plugins->method('commands')->willReturn($pluginCommands);

        return new ConsoleApplicationFactory($container, $plugins, $logger ?? new RecordingLogger());
    }
}
