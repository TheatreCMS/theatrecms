<?php

namespace TheatreCMS\Tests\Unit\Console;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TheatreCMS\Console\Command\ScheduleListCommand;
use TheatreCMS\Console\Command\ScheduleRunCommand;
use TheatreCMS\Scheduler\ScheduledTaskRegistry;
use TheatreCMS\Scheduler\ScheduledTaskRunRepository;
use TheatreCMS\Scheduler\Scheduler;
use TheatreCMS\Scheduler\TaskLocker;
use TheatreCMS\Scheduler\TaskResult;
use TheatreCMS\Tests\Fixtures\Scheduler\FakeClock;
use TheatreCMS\Tests\Fixtures\Scheduler\SchedulerSchema;
use TheatreCMS\Tests\Fixtures\Scheduler\StubTaskRunner;

class ScheduleCommandsTest extends TestCase
{
    use SchedulerSchema;

    private ScheduledTaskRegistry $registry;
    private ScheduledTaskRunRepository $runs;
    private StubTaskRunner $runner;
    private FakeClock $clock;
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->registry = new ScheduledTaskRegistry();
        $this->runs = new ScheduledTaskRunRepository($this->createSchedulerConnection());
        $this->runner = new StubTaskRunner();
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')));
        $this->scheduler = new Scheduler(
            $this->registry,
            $this->runs,
            $this->runner,
            new TaskLocker(sys_get_temp_dir() . '/theatrecms-schedule-commands-test'),
            $this->clock,
        );
    }

    public function testRunPrintsOutcomesAndFailsWhenATaskFails(): void
    {
        $this->registry->register('good', 'good:run', '1h');
        $this->registry->register('bad', 'bad:run', '1h');
        $this->runner->results['bad'] = new TaskResult(5, 'nope');

        $tester = new CommandTester(new ScheduleRunCommand($this->scheduler));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertSame("✔ good: good:run\n✘ bad: bad:run (exit code 5)\n", $tester->getDisplay(true));
    }

    public function testRunSucceedsQuietlyWhenNothingIsDue(): void
    {
        $this->registry->register('good', 'good:run', '1h');
        $this->scheduler->runDue();
        $this->clock->advance('+5 minutes');

        $tester = new CommandTester(new ScheduleRunCommand($this->scheduler));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame('', $tester->getDisplay());

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertSame("- good: not due\n", $tester->getDisplay(true));
    }

    public function testListShowsLastAndNextRuns(): void
    {
        $this->registry->register('sync', 'tessitura:sync --upcoming', '15m');
        $this->scheduler->runDue();
        $this->runs->markStarted('nightly', $this->clock->now()->modify('-1 hour'));
        $this->runs->markFinished('nightly', false, 1, '', $this->clock->now());
        $this->registry->register('nightly', 'media:backfill', '1d');
        $this->registry->register('never', 'list', 90);

        $tester = new CommandTester(new ScheduleListCommand($this->registry, $this->runs, $this->scheduler));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay(true);
        $this->assertMatchesRegularExpression(
            '/sync +\| tessitura:sync --upcoming \| 15m +\| 2026-10-01 12:00 +\| success +\| 2026-10-01 12:15/',
            $display,
        );
        $this->assertMatchesRegularExpression(
            '/nightly +\| media:backfill +\| 1d +\| 2026-10-01 11:00 +\| failed +\| 2026-10-02 11:00/',
            $display,
        );
        $this->assertMatchesRegularExpression('/never +\| list +\| 90s +\| +\| never run +\| next schedule:run/', $display);
    }

    public function testListWithNoTasks(): void
    {
        $tester = new CommandTester(new ScheduleListCommand($this->registry, $this->runs, $this->scheduler));
        $tester->execute([]);

        $this->assertSame("No scheduled tasks are registered.\n", $tester->getDisplay(true));
    }
}
