<?php

use TheatreCMS\Scheduler\ScheduledTaskRegistry;

if (!function_exists('register_scheduled_task')) {
    /**
     * Runs a console command on an interval, through `bin/theatrecms schedule:run` (called by cron
     * every minute). Call it from a plugin's register() or a theme's functions.php.
     *
     * @param string     $name     Unique task name, e.g. `tessitura-sync`
     * @param string     $command  Console command line, e.g. `tessitura:sync --upcoming`
     * @param string|int $interval Seconds, or a duration such as `15m`, `1h` or `1d` (at least one minute)
     * @param int        $timeout  Seconds a run may take before it is stopped
     */
    function register_scheduled_task(string $name, string $command, string|int $interval, int $timeout = 3600): void
    {
        ScheduledTaskRegistry::getInstance()->register($name, $command, $interval, $timeout);
    }
}
