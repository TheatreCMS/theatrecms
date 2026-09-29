<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

use DI\Container;
use TheatreCMS\Plugin\AbstractPlugin;

class ThrowsOnDeclarePlugin extends AbstractPlugin
{
    public function register(Container $container): void
    {
        CallLog::$calls[] = 'throws-on-declare:register';
    }

    public function entityPaths(): array
    {
        throw new \RuntimeException('declare exploded');
    }
}
