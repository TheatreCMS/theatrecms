<?php

namespace TheatreCMS\Tests\Unit;

use DI\Container;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Csrf\Guard;
use Slim\Views\Twig;
use TheatreCMS\Controllers;
use TheatreCMS\DI\ServiceRegistrar;
use TheatreCMS\Repositories;
use TheatreCMS\Services;
use TheatreCMS\Taxonomy\TermArchiveQuery;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * Resolves every service the registrar wires, so a missing or mistyped dependency in any
 * factory fails here instead of on the first request that happens to need it.
 */
class ServiceRegistrarTest extends TestCase
{
    use UsesSqliteEntityManager;

    /**
     * @return array<string, array{class-string}>
     */
    public static function serviceProvider(): array
    {
        $ids = [
            Controllers\LoginController::class,
            Controllers\UsersController::class,
            Controllers\ProfileController::class,
            Controllers\ProductionController::class,
            Controllers\SeasonController::class,
            Controllers\EventController::class,
            Controllers\PostController::class,
            Controllers\PageController::class,
            Controllers\MenuController::class,
            Controllers\VenueController::class,
            Controllers\PersonController::class,
            Controllers\SponsorController::class,
            Controllers\TermController::class,
            Controllers\WorksController::class,
            Controllers\ImageUploadController::class,
            Controllers\MediaController::class,
            Controllers\LinkPreviewController::class,
            Controllers\SettingsController::class,
            Controllers\ThemesController::class,
            Repositories\EventRepository::class,
            Repositories\ExternalReferenceRepository::class,
            Repositories\MediaRepository::class,
            Repositories\MenuRepository::class,
            Repositories\PageRepository::class,
            Repositories\WorkRepository::class,
            Repositories\SponsorRepository::class,
            TermArchiveQuery::class,
            Services\ImageBackfillService::class,
            Services\MediaVariantBackfillService::class,
            Services\MediaFilenameBackfillService::class,
            Twig::class,
        ];

        return array_combine($ids, array_map(static fn(string $id): array => [$id], $ids));
    }

    #[DataProvider('serviceProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegisteredServiceResolves(string $id): void
    {
        $container = $this->buildContainer();

        $this->assertInstanceOf($id, $container->get($id));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCsrfGuardAndTwigMiddlewareFactoryResolve(): void
    {
        $container = $this->buildContainer();

        $this->assertInstanceOf(Guard::class, @$container->get(Guard::class));
        $this->assertIsCallable($container->get(\Slim\Views\TwigMiddleware::class));
    }

    private function buildContainer(): Container
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2));
        }

        $container = new Container([
            'settings' => [
                'themes' => ['dir' => APP_ROOT . '/tests/Fixtures/themes', 'active' => 'bootstrap'],
                'view' => ['template_path' => APP_ROOT . '/templates'],
            ],
        ]);
        ServiceRegistrar::register($container);

        $container->set(EntityManager::class, $this->createSqliteEntityManager());

        return $container;
    }
}
