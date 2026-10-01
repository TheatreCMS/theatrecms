<?php

namespace TheatreCMS\Scheduler;

/**
 * Runs a scheduled task's console command line.
 */
interface TaskRunner
{
    public function run(ScheduledTask $task): TaskResult;
}
