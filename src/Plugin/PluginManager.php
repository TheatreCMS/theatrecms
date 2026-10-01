<?php

namespace TheatreCMS\Plugin;

use DI\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Views\Twig;
use TheatreCMS\Admin\AdminMenuRegistry;
use TheatreCMS\Auth\CapabilityRegistry;
use Twig\Loader\FilesystemLoader;

/**
 * Loads installed plugins (see PluginInterface for the lifecycle) and isolates their failures:
 * a plugin that cannot be instantiated, or that throws while declaring, registering or booting,
 * is logged and skipped so the rest of the site keeps running.
 *
 * Plugins run in package-name order. A plugin that depends on another should hook the
 * `theatrecms/plugins_loaded` action (fired after every plugin has registered) rather than rely
 * on that order.
 */
class PluginManager
{
    /**
     * @var array<string, PluginInterface> package => plugin whose declarations were collected
     */
    private array $plugins = [];

    /**
     * @var array<string, PluginInterface> package => plugin whose register() succeeded
     */
    private array $registered = [];

    /**
     * @var array<string, array{
     *     entityPaths: string[],
     *     twigPaths: array<string, string>,
     *     migrationPaths: string[],
     *     commands: class-string[],
     *     capabilities: array<int, string[]>,
     *     adminMenuItems: \TheatreCMS\Admin\AdminMenuItem[],
     * }> package => what the plugin declared
     */
    private array $declarations = [];

    private bool $discovered = false;

    public function __construct(
        private readonly PluginDiscovery $discovery,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Instantiates every installed plugin and collects its declarations. Safe to call more than once.
     */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        foreach ($this->discovery->discover() as $descriptor) {
            $plugin = $this->instantiate($descriptor);
            if ($plugin === null) {
                continue;
            }

            try {
                $this->declarations[$descriptor->package] = [
                    'entityPaths' => $plugin->entityPaths(),
                    'twigPaths' => $plugin->twigPaths(),
                    'migrationPaths' => $plugin->migrationPaths(),
                    'commands' => $plugin->commands(),
                    'capabilities' => $plugin->capabilities(),
                    'adminMenuItems' => $plugin->adminMenuItems(),
                ];
                $this->plugins[$descriptor->package] = $plugin;
            } catch (\Throwable $e) {
                $this->fail($descriptor->package, 'declaring its extensions', $e);
            }
        }
    }

    /**
     * Calls register() on each plugin, then applies its capabilities and admin menu items.
     * A plugin that throws is skipped: none of its capabilities or menu items are applied and it is not booted.
     */
    public function registerAll(
        Container $container,
        CapabilityRegistry $capabilities,
        AdminMenuRegistry $adminMenu,
    ): void {
        $this->discover();

        foreach ($this->plugins as $package => $plugin) {
            try {
                $plugin->register($container);
            } catch (\Throwable $e) {
                $this->fail($package, 'register()', $e);
                continue;
            }

            foreach ($this->declarations[$package]['capabilities'] as $role => $granted) {
                $capabilities->grant($role, $granted);
            }
            foreach ($this->declarations[$package]['adminMenuItems'] as $item) {
                $adminMenu->add($item);
            }

            $this->registered[$package] = $plugin;
        }
    }

    /**
     * Calls boot() on each successfully registered plugin.
     */
    public function bootAll(App $app): void
    {
        foreach ($this->registered as $package => $plugin) {
            try {
                $plugin->boot($app);
            } catch (\Throwable $e) {
                $this->fail($package, 'boot()', $e);
            }
        }
    }

    /**
     * Adds each plugin's Twig namespaces to the loader (e.g. `@dso/...`).
     */
    public function configureTwig(Twig $twig): void
    {
        $loader = $twig->getLoader();
        if (!$loader instanceof FilesystemLoader) {
            return;
        }

        foreach ($this->twigPaths() as $namespace => $paths) {
            foreach ($paths as $path) {
                $loader->addPath($path, $namespace);
            }
        }
    }

    /**
     * @return array<string, PluginInterface> plugins whose register() succeeded, keyed by package
     */
    public function registered(): array
    {
        return $this->registered;
    }

    /**
     * @return string[]
     */
    public function entityPaths(): array
    {
        return $this->collect('entityPaths');
    }

    /**
     * @return array<string, string[]> Twig namespace => template directories
     */
    public function twigPaths(): array
    {
        $namespaces = [];
        foreach ($this->declarations as $declaration) {
            foreach ($declaration['twigPaths'] as $namespace => $path) {
                $namespaces[$namespace][] = $path;
            }
        }

        return $namespaces;
    }

    /**
     * @return array<string, string[]> package => migration directories
     */
    public function migrationPaths(): array
    {
        return array_filter(array_map(
            static fn(array $declaration): array => $declaration['migrationPaths'],
            $this->declarations,
        ));
    }

    /**
     * @return class-string[]
     */
    public function commands(): array
    {
        return $this->collect('commands');
    }

    private function instantiate(PluginDescriptor $descriptor): ?PluginInterface
    {
        try {
            if (!class_exists($descriptor->class)) {
                throw new \RuntimeException(
                    sprintf(
                        'Plugin class %s is not autoloadable; run bin/composer-local after adding a plugin',
                        $descriptor->class,
                    )
                );
            }

            $plugin = new ($descriptor->class)();
            if (!$plugin instanceof PluginInterface) {
                throw new \RuntimeException(
                    sprintf('%s does not implement %s', $descriptor->class, PluginInterface::class)
                );
            }

            return $plugin;
        } catch (\Throwable $e) {
            $this->fail($descriptor->package, 'loading', $e);
            return null;
        }
    }

    /**
     * @param 'entityPaths'|'commands' $key
     * @return string[]
     */
    private function collect(string $key): array
    {
        $values = [];
        foreach ($this->declarations as $declaration) {
            array_push($values, ...$declaration[$key]);
        }

        return $values;
    }

    private function fail(string $package, string $stage, \Throwable $e): void
    {
        $this->logger->error('Plugin {package} failed while {stage} and was skipped: {message}', [
            'package' => $package,
            'stage' => $stage,
            'message' => $e->getMessage(),
            'exception' => $e,
        ]);
    }
}
