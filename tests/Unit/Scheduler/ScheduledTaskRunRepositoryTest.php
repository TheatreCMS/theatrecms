<?php

namespace TheatreCMS\Tests\Unit\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Scheduler\ScheduledTaskRun;
use TheatreCMS\Scheduler\ScheduledTaskRunRepository;
use TheatreCMS\Tests\Fixtures\Scheduler\SchedulerSchema;

class ScheduledTaskRunRepositoryTest extends TestCase
{
    use SchedulerSchema;

    private ScheduledTaskRunRepository $runs;

    protected function setUp(): void
    {
        $this->runs = new ScheduledTaskRunRepository($this->createSchedulerConnection());
    }

    public function testFindReturnsNullForATaskThatNeverRan(): void
    {
        $this->assertNull($this->runs->find('sync'));
        $this->assertSame([], $this->runs->all());
    }

    public function testRecordsAStartThenAFinish(): void
    {
        $started = new DateTimeImmutable('2026-10-01 08:00:00', new DateTimeZone('America/Chicago'));
        $this->runs->markStarted('sync', $started);

        $run = $this->runs->find('sync');
        $this->assertInstanceOf(ScheduledTaskRun::class, $run);
        $this->assertSame(ScheduledTaskRun::STATUS_RUNNING, $run->lastStatus);
        $this->assertSame('2026-10-01 13:00:00', $run->lastStartedAt?->format('Y-m-d H:i:s'), 'stored in UTC');
        $this->assertSame($started->getTimestamp(), $run->lastStartedAt?->getTimestamp());
        $this->assertNull($run->lastFinishedAt);

        $this->runs->markFinished('sync', false, 3, 'boom', $started->modify('+2 minutes'));

        $run = $this->runs->find('sync');
        $this->assertSame(ScheduledTaskRun::STATUS_FAILED, $run?->lastStatus);
        $this->assertSame(3, $run?->lastExitCode);
        $this->assertSame('boom', $run?->lastOutput);
        $this->assertSame('2026-10-01 13:02:00', $run?->lastFinishedAt?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-01 13:00:00', $run?->lastStartedAt?->format('Y-m-d H:i:s'), 'start kept');
    }

    public function testKeepsTheTailOfLongOutput(): void
    {
        $output = str_repeat('a', 100) . str_repeat('b', ScheduledTaskRunRepository::MAX_OUTPUT);
        $this->runs->markFinished('sync', true, 0, $output, new DateTimeImmutable());

        $this->assertSame(str_repeat('b', ScheduledTaskRunRepository::MAX_OUTPUT), $this->runs->find('sync')?->lastOutput);
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $this->runs->find('sync')?->lastStatus);
    }

    public function testAllIsKeyedByName(): void
    {
        $now = new DateTimeImmutable();
        $this->runs->markStarted('a', $now);
        $this->runs->markStarted('b', $now);

        $this->assertSame(['a', 'b'], array_keys($this->runs->all()));
    }
}
