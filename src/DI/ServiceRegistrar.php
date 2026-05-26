<?php

namespace TheatreCMS\DI;

use DI\Container;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use TheatreCMS\Controllers\EventController;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Controllers\PageController;
use TheatreCMS\Controllers\PostController;
use TheatreCMS\Controllers\LoginController;
use TheatreCMS\Controllers\PersonController;
use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Controllers\SettingsController;
use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Controllers\UsersController;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Controllers\WorksController;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Repositories\UserRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\ThemeManager;
use TheatreCMS\Twig\EditorJsExtension;
use Delight\Auth\Auth;

/**
 * Centralizes the project's dependency-injection wiring.
 *
 * Replaces the previous inline registration in `app/bootstrap.php` with a single entry point
 * that configures shared services, discovers repositories, and wires controllers.
 */
class ServiceRegistrar
{
    public static function register(Container $container): void
    {
        self::registerSharedServices($container);
        self::registerRepositories($container);
        self::registerControllers($container);
    }

    /**
     * Registers the shared services that underpin the admin UI and theme configuration.
     *
     * @param Container $container
     */
    public static function registerSharedServices(Container $container): void
    {
        $container->set(SiteSettings::class, static function (): SiteSettings {
            return new SiteSettings(APP_ROOT . '/app/config.yaml');
        });

        $container->set(HookManager::class, static fn(): HookManager => new HookManager());

        $container->set(ResponseFactoryInterface::class, static fn(): ResponseFactoryInterface => new ResponseFactory());

        $container->set(ThemeManager::class, static function (ContainerInterface $c): ThemeManager {
            $settings = $c->get('settings');
            $themesDir = $settings['themes']['dir'] ?? APP_ROOT . '/themes';
            $activeTheme = $settings['themes']['active'] ?? 'default';
            return new ThemeManager($themesDir, $activeTheme);
        });

        $container->set(Twig::class, static function (ContainerInterface $c): Twig {
            $settings = $c->get('settings');
            $viewSettings = $settings['view'] ?? [];
            $templateDir = $viewSettings['template_path'] ?? APP_ROOT . '/templates';
            $cacheEnabled = $viewSettings['cache_enabled'] ?? false;
            $cache = $cacheEnabled ? ($viewSettings['cache'] ?? false) : false;

            $twig = Twig::create($templateDir, [
                'cache' => $cache,
                'debug' => $viewSettings['debug'] ?? false,
                'auto_reload' => $viewSettings['debug'] ?? false,
            ]);

            $themeManager = $c->get(ThemeManager::class);
            $themeManager->configureTwig($twig, $templateDir);
            $themeManager->loadFunctions();
            $twig->addExtension(new EditorJsExtension(new EditorJsHtmlConverter()));
            $twig->getEnvironment()->addGlobal('theme', $themeManager->getMetadata());

            $siteSettings = $c->get(SiteSettings::class);
            foreach ($siteSettings->all() as $key => $value) {
                $twig->getEnvironment()->addGlobal($key, $value);
            }

            return $twig;
        });

        $container->set(TwigMiddleware::class, static function (): callable {
            return fn(App $app, string $containerKey = 'view') => TwigMiddleware::createFromContainer($app, $containerKey);
        });

        $container->set(Auth::class, static function (ContainerInterface $c): Auth {
            return new Auth($c->get(EntityManager::class)->getConnection()->getNativeConnection());
        });

        $container->set(Guard::class, static function (ContainerInterface $c): Guard {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $responseFactory = $c->get(ResponseFactoryInterface::class);
            return new Guard($responseFactory, 'csrf', null, null, 200, 16, true);
        });
    }

    public static function registerRepositories(Container $container): void
    {
        foreach (self::discoverRepositoryClasses() as $repositoryClass) {
            $container->set($repositoryClass, static function (ContainerInterface $c) use ($repositoryClass) {
                return new $repositoryClass($c->get(EntityManager::class));
            });
        }
    }

    /**
     * Registers controller factories in one place so the bootstrap file stays thin.
     *
     * @param Container $container
     */
    public static function registerControllers(Container $container): void
    {
        foreach (self::controllerFactories() as $controller => $factory) {
            $container->set($controller, $factory);
        }
    }

    /**
     * Maps controller classes to their factory closures.
     */
    private static function controllerFactories(): array
    {
        return [
            LoginController::class => static function (ContainerInterface $c): LoginController {
                return new LoginController(
                    $c->get(UserRepository::class),
                    $c->get(Twig::class),
                    $c->get(Auth::class)
                );
            },
            UsersController::class => static function (ContainerInterface $c): UsersController {
                return new UsersController(
                    $c->get(UserRepository::class),
                    $c->get(Twig::class),
                    $c->get(Auth::class)
                );
            },
            ProductionController::class => static function (ContainerInterface $c): ProductionController {
                return new ProductionController(
                    $c->get(ProductionRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class),
                    $c->get(SeasonRepository::class),
                    $c->get(PersonRepository::class),
                    $c->get(WorkRepository::class),
                    $c->get(SponsorRepository::class),
                    $c->get(VenueRepository::class),
                    $c->get(EventRepository::class)
                );
            },
            SeasonController::class => static function (ContainerInterface $c): SeasonController {
                return new SeasonController(
                    $c->get(SeasonRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class),
                    $c->get(SponsorRepository::class)
                );
            },
            EventController::class => static function (ContainerInterface $c): EventController {
                return new EventController(
                    $c->get(EventRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class),
                    $c->get(ProductionRepository::class),
                    $c->get(VenueRepository::class)
                );
            },
            PostController::class => static function (ContainerInterface $c): PostController {
                return new PostController(
                    $c->get(PostRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class)
                );
            },
            PageController::class => static function (ContainerInterface $c): PageController {
                return new PageController(
                    $c->get(PageRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class)
                );
            },
            VenueController::class => static function (ContainerInterface $c): VenueController {
                return new VenueController(
                    $c->get(VenueRepository::class),
                    $c->get(Twig::class)
                );
            },
            PersonController::class => static function (ContainerInterface $c): PersonController {
                return new PersonController(
                    $c->get(PersonRepository::class),
                    $c->get(Twig::class)
                );
            },
            SponsorController::class => static function (ContainerInterface $c): SponsorController {
                return new SponsorController(
                    $c->get(SponsorRepository::class),
                    $c->get(Twig::class)
                );
            },
            WorksController::class => static function (ContainerInterface $c): WorksController {
                return new WorksController(
                    $c->get(WorkRepository::class),
                    $c->get(Twig::class),
                    $c->get(PersonRepository::class)
                );
            },
            ImageUploadController::class => static fn(): ImageUploadController => new ImageUploadController(),
            SettingsController::class => static function (ContainerInterface $c): SettingsController {
                return new SettingsController(
                    $c->get(SiteSettings::class),
                    $c->get(Twig::class)
                );
            },
        ];
    }

    /**
     * Finds repository classes by scanning the Repositories directory.
     */
    private static function discoverRepositoryClasses(): array
    {
        $directory = APP_ROOT . '/src/Repositories';
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*Repository.php') ?: [];
        $classes = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name === 'BaseRepository') {
                continue;
            }

            $class = sprintf('TheatreCMS\\Repositories\\%s', $name);
            if (!class_exists($class)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return array_unique($classes);
    }
}
