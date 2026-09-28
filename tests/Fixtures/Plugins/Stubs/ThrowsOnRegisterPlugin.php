<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

use DI\Container;
use Delight\Auth\Role;
use Slim\App;
use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Plugin\AbstractPlugin;

class ThrowsOnRegisterPlugin extends AbstractPlugin
{
    public function register(Container $container): void
    {
        throw new \RuntimeException('register exploded');
    }

    public function boot(App $app): void
    {
        CallLog::$calls[] = 'throws-on-register:boot';
    }

    public function capabilities(): array
    {
        return [Role::ADMIN => ['manage_broken']];
    }

    public function adminMenuItems(): array
    {
        return [new AdminMenuItem('Broken', 'admin/broken')];
    }
}
