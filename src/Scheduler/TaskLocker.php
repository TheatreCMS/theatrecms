<?php

namespace TheatreCMS\Scheduler;

/**
 * A non-blocking file lock per task, so a run still in progress is never started again by the next
 * minute's `schedule:run`. The lock is released when the process holding it exits, even if it crashes.
 */
class TaskLocker
{
    public function __construct(private readonly string $lockDirectory)
    {
    }

    /**
     * @return resource|null a handle to pass to release(), or null if another run holds the lock
     */
    public function acquire(string $taskName)
    {
        if (!is_dir($this->lockDirectory) && !@mkdir($this->lockDirectory, 0775, true) && !is_dir($this->lockDirectory)) {
            throw new \RuntimeException(sprintf('Cannot create the scheduler lock directory %s.', $this->lockDirectory));
        }

        $handle = fopen($this->path($taskName), 'c');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open the lock file for scheduled task "%s".', $taskName));
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    public function release($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function path(string $taskName): string
    {
        return rtrim($this->lockDirectory, '/') . '/schedule-' . preg_replace('/[^a-z0-9._-]/', '_', $taskName) . '.lock';
    }
}
