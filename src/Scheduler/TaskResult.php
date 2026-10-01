<?php

namespace TheatreCMS\Scheduler;

final class TaskResult
{
    /**
     * @param int|null $exitCode null when the command couldn't be started or was stopped
     */
    public function __construct(
        public readonly ?int $exitCode,
        public readonly string $output,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
