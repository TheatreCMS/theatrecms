<?php

// APP_ROOT is defined at runtime by app/bootstrap.php (and www/index.php); declare it here so PHPStan
// can resolve the src/ code that uses it.
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}
