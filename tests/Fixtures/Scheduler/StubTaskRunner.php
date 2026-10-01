<?php

namespace TheatreCMS\Tests\Fixtures\Scheduler;

use TheatreCMS\Scheduler\ScheduledTask;
use TheatreCMS\Scheduler\TaskResult;
use TheatreCMS\Scheduler\TaskRunner;

/**
 * Returns a canned result per task name (exit code 0 by default) and records what it ran.
 */
final class StubTaskRunner implements TaskRunner
{
    /** @var string[] */
    public array $ran = [];

    /**
     * @param array<string, TaskResult|\Throwable> $results
     */
    public function __construct(public array $results = [])
    {
    }

    public function run(ScheduledTask $task): TaskResult
    {
        $this->ran[] = $task->name;
        $result = $this->results[$task->name] ?? new TaskResult(0, 'ok');
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}
