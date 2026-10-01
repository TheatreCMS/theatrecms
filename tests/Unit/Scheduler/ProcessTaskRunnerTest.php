<?php

namespace TheatreCMS\Tests\Unit\Scheduler;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Scheduler\ProcessTaskRunner;
use TheatreCMS\Scheduler\ScheduledTask;

class ProcessTaskRunnerTest extends TestCase
{
    private ProcessTaskRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new ProcessTaskRunner(
            dirname(__DIR__, 2) . '/Fixtures/Scheduler/console-stub.php',
            sys_get_temp_dir(),
        );
    }

    public function testCapturesOutputAndExitCode(): void
    {
        $result = $this->runner->run(new ScheduledTask('echo', 'echo hello', 60));

        $this->assertTrue($result->succeeded());
        $this->assertSame('hello', $result->output);
    }

    public function testReportsAFailingCommand(): void
    {
        $result = $this->runner->run(new ScheduledTask('fail', 'fail 3', 60));

        $this->assertFalse($result->succeeded());
        $this->assertSame(3, $result->exitCode);
        $this->assertSame('something broke', $result->output);
    }

    public function testStopsACommandThatOverrunsItsTimeout(): void
    {
        $result = $this->runner->run(new ScheduledTask('sleep', 'sleep 5', 60, 1));

        $this->assertFalse($result->succeeded());
        $this->assertNull($result->exitCode);
        $this->assertStringContainsString('1-second timeout', $result->output);
    }
}
