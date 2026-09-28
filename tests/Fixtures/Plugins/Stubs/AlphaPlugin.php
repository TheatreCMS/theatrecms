<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

use DI\Container;
use Delight\Auth\Role;
use Slim\App;
use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Plugin\AbstractPlugin;

class AlphaPlugin extends AbstractPlugin
{
    public function register(Container $container): void
    {
        CallLog::$calls[] = 'alpha:register';
    }

    public function boot(App $app): void
    {
        CallLog::$calls[] = 'alpha:boot';
    }

    public function entityPaths(): array
    {
        return ['/alpha/Models'];
    }

    public function twigPaths(): array
    {
        return ['shared' => '/alpha/templates'];
    }

    public function migrationPaths(): array
    {
        return ['/alpha/migrations'];
    }

    public function commands(): array
    {
        return ['Alpha\\Command'];
    }

    public function capabilities(): array
    {
        return [Role::ADMIN => ['manage_alpha']];
    }

    public function adminMenuItems(): array
    {
        return [new AdminMenuItem('Alpha', 'admin/alpha')];
    }
}
