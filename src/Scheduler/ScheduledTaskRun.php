<?php

namespace TheatreCMS\Scheduler;

use DateTimeImmutable;

/**
 * The recorded state of a scheduled task's most recent run.
 */
final class ScheduledTaskRun
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $name,
        public readonly ?DateTimeImmutable $lastStartedAt,
        public readonly ?DateTimeImmutable $lastFinishedAt,
        public readonly ?string $lastStatus,
        public readonly ?int $lastExitCode,
        public readonly string $lastOutput,
    ) {
    }
}
