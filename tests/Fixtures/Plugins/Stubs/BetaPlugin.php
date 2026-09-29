<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

use DI\Container;
use Slim\App;
use TheatreCMS\Plugin\AbstractPlugin;

class BetaPlugin extends AbstractPlugin
{
    public function register(Container $container): void
    {
        CallLog::$calls[] = 'beta:register';
    }

    public function boot(App $app): void
    {
        CallLog::$calls[] = 'beta:boot';
    }

    public function entityPaths(): array
    {
        return ['/beta/Models'];
    }

    public function twigPaths(): array
    {
        return ['shared' => '/beta/templates'];
    }
}
