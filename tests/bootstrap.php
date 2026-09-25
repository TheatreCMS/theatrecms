<?php

use TheatreCMS\Theme\HookManager;

define( 'SRC_DIR', dirname(__DIR__));

require_once SRC_DIR . '/vendor/autoload.php';
require_once SRC_DIR . '/tests/Includes/TestCase.php';
require_once SRC_DIR . '/app/hooks.php';

HookManager::setInstance(new HookManager());
