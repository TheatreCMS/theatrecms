<?php

namespace TheatreCMS\DI;

use DI\Container;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use TheatreCMS\Auth\AuthorizationService;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\Controllers\EventController;
use TheatreCMS\Controllers\ImageUploadController;
use TheatreCMS\Controllers\LinkPreviewController;
use TheatreCMS\Controllers\MenuController;
use TheatreCMS\Controllers\PageController;
use TheatreCMS\Controllers\PostController;
use TheatreCMS\Controllers\LoginController;
use TheatreCMS\Controllers\PersonController;
use TheatreCMS\Controllers\ProductionController;
use TheatreCMS\Controllers\ProfileController;
use TheatreCMS\Controllers\SeasonController;
use TheatreCMS\Controllers\SettingsController;
use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Controllers\UsersController;
use TheatreCMS\Controllers\VenueController;
use TheatreCMS\Controllers\WorksController;
use TheatreCMS\Menus\MenuItemResolver;
use TheatreCMS\Settings\SiteSettings;
use TheatreCMS\Repositories\EventRepository;
use TheatreCMS\Repositories\MenuRepository;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\PersonRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Repositories\SponsorRepository;
use TheatreCMS\Repositories\UserRepository;
use TheatreCMS\Repositories\VenueRepository;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Services\ImageUploadService;
use TheatreCMS\Services\LinkPreviewService;
use TheatreCMS\Text\EditorJsHtmlConverter;
use TheatreCMS\Theme\ContentResolver;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\DateResolver;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\MenuLocationRegistry;
use TheatreCMS\Theme\FeaturedImageResolver;
use TheatreCMS\Theme\PermalinkResolver;
use TheatreCMS\Theme\SlugResolver;
use TheatreCMS\Theme\StructuredDataBuilder;
use TheatreCMS\Theme\TemplateResolver;
use TheatreCMS\Theme\ThemeManager;
use TheatreCMS\Theme\TitleResolver;
use TheatreCMS\Twig\CapabilityExtension;
use TheatreCMS\Twig\ContentExtension;
use TheatreCMS\Twig\DateExtension;
use TheatreCMS\Twig\EditorJsExtension;
use TheatreCMS\Twig\FeaturedImageExtension;
use TheatreCMS\Twig\MenuExtension;
use TheatreCMS\Twig\PermalinkExtension;
use TheatreCMS\Twig\SlugExtension;
use TheatreCMS\Twig\ThemeHeadExtension;
use TheatreCMS\Twig\TitleExtension;
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

        $container->set(TitleResolver::class, static fn(): TitleResolver => new TitleResolver());

        $container->set(SlugResolver::class, static fn(): SlugResolver => new SlugResolver());

        $container->set(FeaturedImageResolver::class, static fn(): FeaturedImageResolver => new FeaturedImageResolver());

        $container->set(ContentTypeRegistry::class, static function (ContainerInterface $c): ContentTypeRegistry {
            $settings = $c->get('settings');
            return new ContentTypeRegistry($settings['content_types'] ?? []);
        });

        $container->set(PermalinkResolver::class, static function (ContainerInterface $c): PermalinkResolver {
            return new PermalinkResolver($c->get(ContentTypeRegistry::class));
        });

        $container->set(DateResolver::class, static fn(): DateResolver => new DateResolver());

        $container->set(TemplateResolver::class, static function (ContainerInterface $c): TemplateResolver {
            return new TemplateResolver($c->get(TitleResolver::class));
        });

        $container->set(EditorJsHtmlConverter::class, static fn(): EditorJsHtmlConverter => new EditorJsHtmlConverter());

        $container->set(ContentResolver::class, static function (ContainerInterface $c): ContentResolver {
            return new ContentResolver($c->get(EditorJsHtmlConverter::class));
        });

        $container->set(StructuredDataBuilder::class, static function (ContainerInterface $c): StructuredDataBuilder {
            return new StructuredDataBuilder($c->get(SiteSettings::class), $c->get(EditorJsHtmlConverter::class));
        });

        $container->set(ThemeHeadExtension::class, static function (ContainerInterface $c): ThemeHeadExtension {
            return new ThemeHeadExtension($c->get(StructuredDataBuilder::class));
        });

        $container->set(CapabilityRegistry::class, static fn(): CapabilityRegistry => new CapabilityRegistry());

        $container->set(AuthorizationService::class, static function (ContainerInterface $c): AuthorizationService {
            return new AuthorizationService($c->get(Auth::class), $c->get(CapabilityRegistry::class));
        });

        $container->set(ImageUploadService::class, static fn(): ImageUploadService => new ImageUploadService(APP_ROOT . '/www'));

        $container->set(LinkPreviewService::class, static fn(): LinkPreviewService => new LinkPreviewService(new \GuzzleHttp\Client()));

        $container->set(MenuLocationRegistry::class, static fn(): MenuLocationRegistry => new MenuLocationRegistry());

        $container->set(MenuItemResolver::class, static function (ContainerInterface $c): MenuItemResolver {
            return new MenuItemResolver(
                $c->get(PageRepository::class),
                $c->get(PostRepository::class),
                $c->get(ProductionRepository::class),
                $c->get(SeasonRepository::class),
                $c->get(PermalinkResolver::class),
                $c->get(ContentTypeRegistry::class)
            );
        });

        $container->set(ThemeManager::class, static function (ContainerInterface $c): ThemeManager {
            $settings = $c->get('settings');
            $themesDir = $settings['themes']['dir'] ?? APP_ROOT . '/themes';
            $activeTheme = $settings['themes']['active'] ?? 'default';
            return new ThemeManager($themesDir, $activeTheme);
        });

        //initialize Twig and configure extensions
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
            $twig->addExtension(new EditorJsExtension($c->get(EditorJsHtmlConverter::class)));
            $twig->addExtension(new MenuExtension($c->get(MenuRepository::class), $c->get(MenuItemResolver::class)));
            $twig->addExtension(new CapabilityExtension($c->get(AuthorizationService::class)));
            $twig->addExtension($c->get(ThemeHeadExtension::class));
            $twig->addExtension(new TitleExtension($c->get(TitleResolver::class)));
            $twig->addExtension(new SlugExtension($c->get(SlugResolver::class)));
            $twig->addExtension(new FeaturedImageExtension($c->get(FeaturedImageResolver::class)));
            $twig->addExtension(new PermalinkExtension($c->get(PermalinkResolver::class)));
            $twig->addExtension(new DateExtension($c->get(DateResolver::class)));
            $twig->addExtension(new ContentExtension($c->get(ContentResolver::class)));
            $twig->getEnvironment()->addGlobal('theme', $themeManager->getMetadata());

            $auth = $c->get(Auth::class);
            $twig->getEnvironment()->addGlobal('current_user', [
                'name' => $auth->getUsername() ?? $auth->getEmail(),
            ]);

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
    }

    public static function registerRepositories(Container $container): void
    {
        foreach (self::discoverRepositoryClasses() as $repositoryClass) {
            $container->set($repositoryClass, static function (ContainerInterface $c) use ($repositoryClass) {
                return new $repositoryClass($c->get(EntityManager::class));
            });
        }

        $container->set(UserRepository::class, static function (ContainerInterface $c): UserRepository {
            $entityManager = $c->get(EntityManager::class);

            return new UserRepository($entityManager->getConnection(), $c->get(Auth::class));
        });
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
            ProfileController::class => static function (ContainerInterface $c): ProfileController {
                return new ProfileController(
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
                    $c->get(SponsorRepository::class),
                    $c->get(ImageUploadService::class)
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
                    $c->get(Twig::class),
                    $c->get(ImageUploadService::class)
                );
            },
            PageController::class => static function (ContainerInterface $c): PageController {
                return new PageController(
                    $c->get(PageRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class)
                );
            },
            MenuController::class => static function (ContainerInterface $c): MenuController {
                return new MenuController(
                    $c->get(MenuRepository::class),
                    $c->get(EntityManager::class),
                    $c->get(Twig::class),
                    $c->get(MenuLocationRegistry::class),
                    $c->get(MenuItemResolver::class),
                    $c->get(PageRepository::class),
                    $c->get(PostRepository::class),
                    $c->get(ProductionRepository::class),
                    $c->get(SeasonRepository::class)
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
                    $c->get(Twig::class),
                    $c->get(ImageUploadService::class)
                );
            },
            WorksController::class => static function (ContainerInterface $c): WorksController {
                return new WorksController(
                    $c->get(WorkRepository::class),
                    $c->get(Twig::class),
                    $c->get(PersonRepository::class)
                );
            },
            ImageUploadController::class => static function (ContainerInterface $c): ImageUploadController {
                return new ImageUploadController($c->get(ImageUploadService::class));
            },
            LinkPreviewController::class => static function (ContainerInterface $c): LinkPreviewController {
                return new LinkPreviewController($c->get(LinkPreviewService::class));
            },
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
            if ($name === 'BaseRepository' || $name === 'UserRepository') {
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
