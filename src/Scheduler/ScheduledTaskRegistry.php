<?php

namespace TheatreCMS\Scheduler;

use InvalidArgumentException;

/**
 * Tasks that `bin/theatrecms schedule:run` runs on an interval. Core, plugins and themes register
 * them with `register_scheduled_task()` (see `app/scheduler.php` and documentation/console.md).
 */
class ScheduledTaskRegistry
{
    /**
     * @var array<string, ScheduledTask> name => task
     */
    private array $tasks = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The ScheduledTaskRegistry has not been initialized.');
        }

        return self::$instance;
    }

    /**
     * @param string     $name     Unique name: lowercase letters, digits, `-`, `_`, `.` or `:`
     * @param string     $command  Console command line to run, e.g. `tessitura:sync --upcoming`
     * @param string|int $interval Seconds, or a duration such as `15m`, `1h` or `1d` (at least one minute)
     * @param int        $timeout  Seconds a run may take before it is stopped
     *
     * @throws InvalidArgumentException for an invalid name, empty command or invalid interval
     */
    public function register(string $name, string $command, string|int $interval, int $timeout = 3600): ScheduledTask
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._:-]{0,190}$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid scheduled task name "%s"; use lowercase letters, digits, "-", "_", "." or ":".',
                $name,
            ));
        }

        if (trim($command) === '') {
            throw new InvalidArgumentException(sprintf('Scheduled task "%s" has an empty command.', $name));
        }

        if ($timeout < 1) {
            throw new InvalidArgumentException(sprintf('Scheduled task "%s" needs a positive timeout.', $name));
        }

        return $this->tasks[$name] = new ScheduledTask(
            $name,
            trim($command),
            ScheduledTask::parseInterval($interval),
            $timeout,
        );
    }

    /**
     * @return array<string, ScheduledTask> name => task, in registration order
     */
    public function all(): array
    {
        return $this->tasks;
    }

    public function has(string $name): bool
    {
        return isset($this->tasks[$name]);
    }

    public function get(string $name): ?ScheduledTask
    {
        return $this->tasks[$name] ?? null;
    }
}
