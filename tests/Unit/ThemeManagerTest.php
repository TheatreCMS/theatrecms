<?php

namespace TheatreCMS\Tests;

use DI\Container;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Auth\CapabilityRegistry;
use TheatreCMS\DI\ServiceRegistrar;
use TheatreCMS\Theme\HookManager;
use TheatreCMS\Theme\ImageSizeRegistry;
use TheatreCMS\Theme\MenuLocationRegistry;
use TheatreCMS\Theme\ThemeManager;

class ThemeManagerTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCliStyleContainerResolutionSeesThemeRegistrationsAndLoadsFunctionsOnlyOnce(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 2));
        }
        if (!defined('SRC_DIR')) {
            define('SRC_DIR', APP_ROOT);
        }

        require_once APP_ROOT . '/vendor/autoload.php';
        require_once APP_ROOT . '/app/hooks.php';
        require_once SRC_DIR . '/app/menu-locations.php';
        require_once SRC_DIR . '/app/image-sizes.php';

        $container = new Container([
            'settings' => [
                'themes' => [
                    'dir' => SRC_DIR . '/tests/Fixtures/themes',
                    'active' => 'bootstrap',
                ],
            ],
        ]);
        ServiceRegistrar::register($container);

        HookManager::setInstance($container->get(HookManager::class));
        MenuLocationRegistry::setInstance($container->get(MenuLocationRegistry::class));
        CapabilityRegistry::setInstance($container->get(CapabilityRegistry::class));
        ImageSizeRegistry::setInstance($container->get(ImageSizeRegistry::class));

        $themeManager = $container->get(ThemeManager::class);
        $themeManager->loadFunctions();

        $this->assertTrue($container->get(ImageSizeRegistry::class)->has('theme-card'));
        $this->assertTrue($container->get(MenuLocationRegistry::class)->has('bootstrap-test'));
        $this->assertSame(
            [999],
            $container->get(CapabilityRegistry::class)->rolesFor('theme-bootstrap-test')
        );
        $this->assertSame(
            'value-theme',
            $container->get(HookManager::class)->applyFilters('theatrecms/bootstrap-test', 'value')
        );
        $this->assertSame(1, $GLOBALS['theme_functions_load_count']);

        $this->assertSame($themeManager, $container->get(ThemeManager::class));
        $container->get(ThemeManager::class)->loadFunctions();

        $this->assertSame(1, $GLOBALS['theme_functions_load_count']);
    }
}
