<?php

namespace TheatreCMS\Scheduler;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runs every registered task that is due. A task is due when it has never run, or when at least
 * its interval has passed since its last run started.
 */
class Scheduler
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_NOT_DUE = 'not-due';
    public const OUTCOME_LOCKED = 'locked';

    public function __construct(
        private readonly ScheduledTaskRegistry $registry,
        private readonly ScheduledTaskRunRepository $runs,
        private readonly TaskRunner $runner,
        private readonly TaskLocker $locker,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param callable(ScheduledTask, string, ?TaskResult): void|null $onOutcome called after each task
     * @return array<string, string> task name => outcome (one of the OUTCOME_* constants)
     */
    public function runDue(?callable $onOutcome = null): array
    {
        $outcomes = [];

        foreach ($this->registry->all() as $name => $task) {
            [$outcome, $result] = $this->runIfDue($task);
            $outcomes[$name] = $outcome;
            if ($onOutcome !== null) {
                $onOutcome($task, $outcome, $result);
            }
        }

        return $outcomes;
    }

    public function nextDueAt(ScheduledTask $task, ?ScheduledTaskRun $run): ?DateTimeImmutable
    {
        return $run?->lastStartedAt?->modify(sprintf('+%d seconds', $task->intervalSeconds));
    }

    /**
     * @return array{0: string, 1: ?TaskResult}
     */
    private function runIfDue(ScheduledTask $task): array
    {
        $now = $this->clock->now();
        $nextDue = $this->nextDueAt($task, $this->runs->find($task->name));
        if ($nextDue !== null && $nextDue > $now) {
            return [self::OUTCOME_NOT_DUE, null];
        }

        $lock = $this->locker->acquire($task->name);
        if ($lock === null) {
            return [self::OUTCOME_LOCKED, null];
        }

        try {
            $this->runs->markStarted($task->name, $now);

            try {
                $result = $this->runner->run($task);
            } catch (\Throwable $e) {
                $result = new TaskResult(null, $e->getMessage());
            }

            $this->runs->markFinished(
                $task->name,
                $result->succeeded(),
                $result->exitCode,
                $result->output,
                $this->clock->now(),
            );
        } finally {
            $this->locker->release($lock);
        }

        if (!$result->succeeded()) {
            $this->logger->error('Scheduled task {task} failed with exit code {exit_code}: {output}', [
                'task' => $task->name,
                'exit_code' => $result->exitCode ?? 'none',
                'output' => $result->output,
            ]);

            return [self::OUTCOME_FAILED, $result];
        }

        return [self::OUTCOME_SUCCESS, $result];
    }
}
