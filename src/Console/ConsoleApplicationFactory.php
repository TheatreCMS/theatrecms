<?php

namespace TheatreCMS\Console;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use TheatreCMS\Console\Command\MediaBackfillCommand;
use TheatreCMS\Console\Command\MediaRegenerateThumbnailsCommand;
use TheatreCMS\Console\Command\MediaRenameFilenamesCommand;
use TheatreCMS\Console\Command\MigrateCommand;
use TheatreCMS\Console\Command\ScheduleListCommand;
use TheatreCMS\Console\Command\ScheduleRunCommand;
use TheatreCMS\Console\Command\UserCreateAdminCommand;
use TheatreCMS\Plugin\PluginManager;

/**
 * Builds the `bin/theatrecms` application from core's commands and every plugin's `commands()`.
 *
 * Commands are registered lazily: the name, aliases and description come from each class's
 * #[AsCommand] attribute, and the class is only built (through the DI container, so constructor
 * dependencies are injected) when that command runs. `bin/theatrecms list` builds none of them.
 */
class ConsoleApplicationFactory
{
    /**
     * @var class-string<Command>[]
     */
    public const CORE_COMMANDS = [
        MigrateCommand::class,
        UserCreateAdminCommand::class,
        MediaBackfillCommand::class,
        MediaRegenerateThumbnailsCommand::class,
        MediaRenameFilenamesCommand::class,
        ScheduleRunCommand::class,
        ScheduleListCommand::class,
    ];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly PluginManager $plugins,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function create(): Application
    {
        $application = new Application('TheatreCMS');

        foreach (self::CORE_COMMANDS as $class) {
            $application->addCommand($this->lazy($class));
        }

        foreach ($this->plugins->commands() as $class) {
            try {
                $application->addCommand($this->lazy($class));
            } catch (\Throwable $e) {
                $this->logger->error('Plugin command {class} was skipped: {message}', [
                    'class' => $class,
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return $application;
    }

    private function lazy(string $class): LazyCommand
    {
        if (!class_exists($class) || !is_subclass_of($class, Command::class)) {
            throw new \InvalidArgumentException(sprintf('%s is not a %s class.', $class, Command::class));
        }

        $attributes = (new ReflectionClass($class))->getAttributes(AsCommand::class);
        if ($attributes === []) {
            throw new \InvalidArgumentException(sprintf('%s has no #[AsCommand] attribute.', $class));
        }

        /** @var AsCommand $definition */
        $definition = $attributes[0]->newInstance();

        // AsCommand packs aliases into the name ("name|alias"); a leading "|" marks the command hidden.
        $names = explode('|', $definition->name);
        $hidden = $names[0] === '';
        if ($hidden) {
            array_shift($names);
        }
        $name = (string) array_shift($names);

        return new LazyCommand(
            $name,
            $names,
            (string) $definition->description,
            $hidden,
            fn (): Command => $this->container->get($class),
        );
    }
}
