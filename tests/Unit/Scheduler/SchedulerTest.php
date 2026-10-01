<?php

namespace TheatreCMS\Tests\Unit\Scheduler;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\ScheduledTaskRun;
use TheatreCMS\Scheduler\ScheduledTask;
use TheatreCMS\Repositories\ScheduledTaskRunRepository;
use TheatreCMS\Scheduler\ScheduledTaskRegistry;
use TheatreCMS\Scheduler\Scheduler;
use TheatreCMS\Scheduler\TaskLocker;
use TheatreCMS\Scheduler\TaskResult;
use TheatreCMS\Tests\Fixtures\Plugins\Stubs\RecordingLogger;
use TheatreCMS\Tests\Fixtures\Scheduler\FakeClock;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;
use TheatreCMS\Tests\Fixtures\Scheduler\StubTaskRunner;

class SchedulerTest extends TestCase
{
    use UsesSqliteEntityManager;

    private ScheduledTaskRegistry $registry;
    private ScheduledTaskRunRepository $runs;
    private StubTaskRunner $runner;
    private FakeClock $clock;
    private RecordingLogger $logger;
    private string $lockDirectory;
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->registry = new ScheduledTaskRegistry();
        $this->runs = new ScheduledTaskRunRepository($this->createSqliteEntityManager());
        $this->runner = new StubTaskRunner();
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-01 12:00:00', new \DateTimeZone('UTC')));
        $this->logger = new RecordingLogger();
        $this->lockDirectory = sys_get_temp_dir() . '/theatrecms-scheduler-test-' . bin2hex(random_bytes(4));

        $this->scheduler = new Scheduler(
            $this->registry,
            $this->runs,
            $this->runner,
            new TaskLocker($this->lockDirectory),
            $this->clock,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->lockDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->lockDirectory)) {
            rmdir($this->lockDirectory);
        }
    }

    public function testRunsATaskThatNeverRanThenWaitsForItsInterval(): void
    {
        $this->registry->register('sync', 'tessitura:sync', '15m');

        $this->assertSame(['sync' => Scheduler::OUTCOME_SUCCESS], $this->scheduler->runDue());

        $this->clock->advance('+14 minutes');
        $this->assertSame(['sync' => Scheduler::OUTCOME_NOT_DUE], $this->scheduler->runDue());

        $this->clock->advance('+1 minute');
        $this->assertSame(['sync' => Scheduler::OUTCOME_SUCCESS], $this->scheduler->runDue());

        $this->assertSame(['sync', 'sync'], $this->runner->ran);
    }

    public function testRecordsSuccess(): void
    {
        $this->registry->register('sync', 'tessitura:sync', '1h');

        $this->scheduler->runDue();

        $run = $this->runs->find('sync');
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $run?->getLastStatus());
        $this->assertSame(0, $run?->getLastExitCode());
        $this->assertSame('ok', $run?->getLastOutput());
        $this->assertEquals($this->clock->now(), $run?->getLastStartedAt());
        $this->assertSame([], $this->logger->records);
    }

    public function testRecordsAndLogsFailure(): void
    {
        $this->registry->register('sync', 'tessitura:sync', '1h');
        $this->runner->results['sync'] = new TaskResult(2, 'API unreachable');

        $this->assertSame(['sync' => Scheduler::OUTCOME_FAILED], $this->scheduler->runDue());

        $run = $this->runs->find('sync');
        $this->assertSame(ScheduledTaskRun::STATUS_FAILED, $run?->getLastStatus());
        $this->assertSame(2, $run?->getLastExitCode());
        $this->assertSame('API unreachable', $run?->getLastOutput());
        $this->assertCount(1, $this->logger->records);
        $this->assertSame('sync', $this->logger->records[0]['context']['task']);
    }

    public function testARunnerExceptionIsAFailureAndOtherTasksStillRun(): void
    {
        $this->registry->register('broken', 'broken', '1h');
        $this->registry->register('fine', 'fine', '1h');
        $this->runner->results['broken'] = new \RuntimeException('could not start');

        $outcomes = $this->scheduler->runDue();

        $this->assertSame(['broken' => Scheduler::OUTCOME_FAILED, 'fine' => Scheduler::OUTCOME_SUCCESS], $outcomes);
        $this->assertNull($this->runs->find('broken')?->getLastExitCode());
        $this->assertSame('could not start', $this->runs->find('broken')?->getLastOutput());
    }

    public function testSkipsATaskWhoseLockIsHeld(): void
    {
        $this->registry->register('sync', 'tessitura:sync', '1h');
        $otherProcess = new TaskLocker($this->lockDirectory);
        $handle = $otherProcess->acquire('sync');
        $this->assertNotNull($handle);

        try {
            $this->assertSame(['sync' => Scheduler::OUTCOME_LOCKED], $this->scheduler->runDue());
            $this->assertSame([], $this->runner->ran);
            $this->assertNull($this->runs->find('sync'));
        } finally {
            $otherProcess->release($handle);
        }

        $this->assertSame(['sync' => Scheduler::OUTCOME_SUCCESS], $this->scheduler->runDue());
    }

    public function testReportsEachOutcomeToTheCallback(): void
    {
        $this->registry->register('sync', 'tessitura:sync', '1h');
        $seen = [];

        $this->scheduler->runDue(function (ScheduledTask $task, string $outcome, ?TaskResult $result) use (&$seen): void {
            $seen[] = [$task->name, $outcome, $result?->exitCode];
        });

        $this->assertSame([['sync', Scheduler::OUTCOME_SUCCESS, 0]], $seen);
    }

    public function testNextDueAt(): void
    {
        $task = new ScheduledTask('sync', 'x', 3600);
        $started = new DateTimeImmutable('2026-10-01 12:00:00');

        $this->assertNull($this->scheduler->nextDueAt($task, null));
        $this->assertEquals($started->modify('+1 hour'), $this->scheduler->nextDueAt(
            $task,
            (new ScheduledTaskRun('sync'))->markStarted($started),
        ));
    }
}
