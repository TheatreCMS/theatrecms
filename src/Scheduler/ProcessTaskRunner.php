<?php

namespace TheatreCMS\Scheduler;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs each task as `php bin/theatrecms <command>` in its own process, so a fatal error, exit() or
 * memory leak in one task can't take down `schedule:run` or the tasks after it.
 */
class ProcessTaskRunner implements TaskRunner
{
    public function __construct(
        private readonly string $consolePath,
        private readonly string $workingDirectory,
        private readonly string $phpBinary = PHP_BINARY,
    ) {
    }

    public function run(ScheduledTask $task): TaskResult
    {
        // The command line comes from register_scheduled_task() calls in code, not from user input.
        $process = Process::fromShellCommandline(
            sprintf('%s %s %s', escapeshellarg($this->phpBinary), escapeshellarg($this->consolePath), $task->command),
            $this->workingDirectory,
            null,
            null,
            $task->timeoutSeconds,
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new TaskResult(null, $this->output($process) . sprintf(
                "\n[schedule:run] Stopped after the %d-second timeout.",
                $task->timeoutSeconds,
            ));
        }

        return new TaskResult($process->getExitCode(), $this->output($process));
    }

    private function output(Process $process): string
    {
        return trim($process->getOutput() . $process->getErrorOutput());
    }
}
