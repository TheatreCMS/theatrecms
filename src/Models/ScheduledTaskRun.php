<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * The most recent run of a task registered with register_scheduled_task(), one row per task,
 * recorded by `bin/theatrecms schedule:run` (see documentation/console.md).
 */
#[Entity]
#[Table(name: 'scheduled_task_runs')]
class ScheduledTaskRun
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /** Longest output kept per run; the tail is kept, since errors usually come last. */
    public const MAX_OUTPUT = 4000;

    #[Id, Column(type: 'string', length: 191)]
    private string $name;

    #[Column(name: 'last_started_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastStartedAt = null;

    #[Column(name: 'last_finished_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastFinishedAt = null;

    #[Column(name: 'last_status', type: 'string', length: 16, nullable: true)]
    private ?string $lastStatus = null;

    #[Column(name: 'last_exit_code', type: 'integer', nullable: true)]
    private ?int $lastExitCode = null;

    #[Column(name: 'last_output', type: 'text', nullable: true)]
    private ?string $lastOutput = null;

    #[Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLastStartedAt(): ?DateTimeImmutable
    {
        return $this->lastStartedAt;
    }

    public function getLastFinishedAt(): ?DateTimeImmutable
    {
        return $this->lastFinishedAt;
    }

    public function getLastStatus(): ?string
    {
        return $this->lastStatus;
    }

    public function getLastExitCode(): ?int
    {
        return $this->lastExitCode;
    }

    public function getLastOutput(): string
    {
        return $this->lastOutput ?? '';
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markStarted(DateTimeImmutable $at): self
    {
        $this->lastStartedAt = $this->local($at);
        $this->lastStatus = self::STATUS_RUNNING;
        $this->updatedAt = $this->lastStartedAt;

        return $this;
    }

    public function markFinished(bool $succeeded, ?int $exitCode, string $output, DateTimeImmutable $at): self
    {
        $this->lastFinishedAt = $this->local($at);
        $this->lastStatus = $succeeded ? self::STATUS_SUCCESS : self::STATUS_FAILED;
        $this->lastExitCode = $exitCode;
        $this->lastOutput = mb_strlen($output) > self::MAX_OUTPUT ? mb_substr($output, -self::MAX_OUTPUT) : $output;
        $this->updatedAt = $this->lastFinishedAt;

        return $this;
    }

    /**
     * Doctrine stores a datetime's wall-clock time without its zone and reads it back in PHP's
     * default zone, so a time from another zone (e.g. an injected UTC clock) is converted first.
     */
    private function local(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
}
