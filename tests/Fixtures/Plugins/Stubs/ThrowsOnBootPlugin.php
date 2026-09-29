<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Stubs;

use Slim\App;
use TheatreCMS\Plugin\AbstractPlugin;

class ThrowsOnBootPlugin extends AbstractPlugin
{
    public function boot(App $app): void
    {
        throw new \RuntimeException('boot exploded');
    }
}
