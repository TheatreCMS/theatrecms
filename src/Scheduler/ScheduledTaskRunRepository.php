<?php

namespace TheatreCMS\Scheduler;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;

/**
 * Last-run state of each scheduled task, in the `scheduled_task_runs` table
 * (migrations/20261001_create_scheduled_task_runs_table.sql). Times are stored in UTC.
 */
class ScheduledTaskRunRepository
{
    public const TABLE = 'scheduled_task_runs';

    /** Longest output kept per run; the tail is kept, since errors usually come last. */
    public const MAX_OUTPUT = 4000;

    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function find(string $name): ?ScheduledTaskRun
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE name = ?',
            [$name],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<string, ScheduledTaskRun> name => run
     */
    public function all(): array
    {
        $runs = [];
        foreach ($this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE) as $row) {
            $runs[$row['name']] = $this->hydrate($row);
        }

        return $runs;
    }

    public function markStarted(string $name, DateTimeInterface $at): void
    {
        $this->save($name, [
            'last_started_at' => $this->format($at),
            'last_status' => ScheduledTaskRun::STATUS_RUNNING,
            'updated_at' => $this->format($at),
        ]);
    }

    public function markFinished(string $name, bool $succeeded, ?int $exitCode, string $output, DateTimeInterface $at): void
    {
        $this->save($name, [
            'last_finished_at' => $this->format($at),
            'last_status' => $succeeded ? ScheduledTaskRun::STATUS_SUCCESS : ScheduledTaskRun::STATUS_FAILED,
            'last_exit_code' => $exitCode,
            'last_output' => mb_strlen($output) > self::MAX_OUTPUT ? mb_substr($output, -self::MAX_OUTPUT) : $output,
            'updated_at' => $this->format($at),
        ]);
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function save(string $name, array $columns): void
    {
        $this->connection->transactional(function (Connection $connection) use ($name, $columns): void {
            $exists = $connection->fetchOne('SELECT 1 FROM ' . self::TABLE . ' WHERE name = ?', [$name]);
            if ($exists !== false) {
                $connection->update(self::TABLE, $columns, ['name' => $name]);
            } else {
                $connection->insert(self::TABLE, ['name' => $name] + $columns);
            }
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ScheduledTaskRun
    {
        return new ScheduledTaskRun(
            (string) $row['name'],
            $this->parse($row['last_started_at'] ?? null),
            $this->parse($row['last_finished_at'] ?? null),
            $row['last_status'] !== null ? (string) $row['last_status'] : null,
            $row['last_exit_code'] !== null ? (int) $row['last_exit_code'] : null,
            (string) ($row['last_output'] ?? ''),
        );
    }

    private function format(DateTimeInterface $at): string
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    private function parse(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(self::FORMAT, (string) $value, new DateTimeZone('UTC'));

        return $parsed === false ? null : $parsed;
    }
}
