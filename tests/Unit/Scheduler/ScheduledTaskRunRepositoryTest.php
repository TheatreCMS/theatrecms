<?php

namespace TheatreCMS\Tests\Unit\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\ScheduledTaskRun;
use TheatreCMS\Repositories\ScheduledTaskRunRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

class ScheduledTaskRunRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private ScheduledTaskRunRepository $runs;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->runs = new ScheduledTaskRunRepository($this->em);
    }

    public function testFindReturnsNullForATaskThatNeverRan(): void
    {
        $this->assertNull($this->runs->find('sync'));
        $this->assertSame([], $this->runs->all());
    }

    public function testRecordsAStartThenAFinish(): void
    {
        // A zone other than PHP's default, as an injected clock might return.
        $started = new DateTimeImmutable('2026-10-01 08:00:00', new DateTimeZone('Pacific/Auckland'));
        $this->runs->markStarted('sync', $started);
        $this->runs->markFinished('sync', false, 3, 'boom', $started->modify('+2 minutes'));
        $this->em->clear();

        $run = $this->runs->find('sync');
        $this->assertInstanceOf(ScheduledTaskRun::class, $run);
        $this->assertSame(ScheduledTaskRun::STATUS_FAILED, $run->getLastStatus());
        $this->assertSame(3, $run->getLastExitCode());
        $this->assertSame('boom', $run->getLastOutput());
        $this->assertSame($started->getTimestamp(), $run->getLastStartedAt()?->getTimestamp(), 'same instant after a round trip');
        $this->assertSame($started->getTimestamp() + 120, $run->getLastFinishedAt()?->getTimestamp());
        $this->assertSame($started->getTimestamp() + 120, $run->getUpdatedAt()->getTimestamp());
    }

    public function testMarkStartedRecordsRunning(): void
    {
        $this->runs->markStarted('sync', new DateTimeImmutable());

        $this->assertSame(ScheduledTaskRun::STATUS_RUNNING, $this->runs->find('sync')?->getLastStatus());
        $this->assertNull($this->runs->find('sync')?->getLastFinishedAt());
    }

    public function testKeepsTheTailOfLongOutput(): void
    {
        $output = str_repeat('a', 100) . str_repeat('b', ScheduledTaskRun::MAX_OUTPUT);
        $this->runs->markFinished('sync', true, 0, $output, new DateTimeImmutable());

        $this->assertSame(str_repeat('b', ScheduledTaskRun::MAX_OUTPUT), $this->runs->find('sync')?->getLastOutput());
        $this->assertSame(ScheduledTaskRun::STATUS_SUCCESS, $this->runs->find('sync')?->getLastStatus());
    }

    public function testAllIsKeyedByName(): void
    {
        $now = new DateTimeImmutable();
        $this->runs->markStarted('a', $now);
        $this->runs->markStarted('b', $now);
        $this->em->clear();

        $runs = $this->runs->all();
        ksort($runs);
        $this->assertSame(['a', 'b'], array_keys($runs));
    }
}
