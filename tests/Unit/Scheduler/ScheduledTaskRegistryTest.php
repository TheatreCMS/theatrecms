<?php

namespace TheatreCMS\Tests\Unit\Scheduler;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Scheduler\ScheduledTask;
use TheatreCMS\Scheduler\ScheduledTaskRegistry;

class ScheduledTaskRegistryTest extends TestCase
{
    /**
     * @return array<string, array{0: string|int, 1: int}>
     */
    public static function validIntervals(): array
    {
        return [
            'integer seconds' => [300, 300],
            'digit string' => ['120', 120],
            'seconds suffix' => ['90s', 90],
            'minutes' => ['15m', 900],
            'hours, upper case' => ['2H', 7200],
            'days with a space' => ['1 d', 86400],
        ];
    }

    #[DataProvider('validIntervals')]
    public function testParsesIntervals(string|int $interval, int $seconds): void
    {
        $this->assertSame($seconds, ScheduledTask::parseInterval($interval));
    }

    /**
     * @return array<string, array{0: string|int}>
     */
    public static function invalidIntervals(): array
    {
        return [
            'under a minute' => [59],
            'under a minute as a duration' => ['30s'],
            'unknown unit' => ['1w'],
            'words' => ['hourly'],
            'negative' => ['-5m'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidIntervals')]
    public function testRejectsInvalidIntervals(string|int $interval): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduledTask::parseInterval($interval);
    }

    public function testRegistersTasksInOrder(): void
    {
        $registry = new ScheduledTaskRegistry();
        $sync = $registry->register('tessitura:sync', '  tessitura:sync --upcoming ', '15m', 600);
        $registry->register('publish', 'content:publish-scheduled', '1m');

        $this->assertSame(['tessitura:sync', 'publish'], array_keys($registry->all()));
        $this->assertSame('tessitura:sync --upcoming', $sync->command);
        $this->assertSame(900, $sync->intervalSeconds);
        $this->assertSame(600, $sync->timeoutSeconds);
        $this->assertTrue($registry->has('publish'));
        $this->assertSame(3600, $registry->get('publish')?->timeoutSeconds);
        $this->assertNull($registry->get('missing'));
    }

    public function testRegisteringANameAgainReplacesTheTask(): void
    {
        $registry = new ScheduledTaskRegistry();
        $registry->register('sync', 'a', '1h');
        $registry->register('sync', 'b', '2h');

        $this->assertCount(1, $registry->all());
        $this->assertSame('b', $registry->get('sync')?->command);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function invalidRegistrations(): array
    {
        return [
            'upper-case name' => ['Sync', 'a', 3600],
            'name with a space' => ['my task', 'a', 3600],
            'empty name' => ['', 'a', 3600],
            'empty command' => ['sync', '   ', 3600],
            'zero timeout' => ['sync', 'a', 0],
        ];
    }

    #[DataProvider('invalidRegistrations')]
    public function testRejectsInvalidRegistrations(string $name, string $command, int $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ScheduledTaskRegistry())->register($name, $command, '1h', $timeout);
    }

    public function testGlobalHelperRegistersOnTheSharedInstance(): void
    {
        require_once dirname(__DIR__, 3) . '/app/scheduler.php';
        $registry = new ScheduledTaskRegistry();
        ScheduledTaskRegistry::setInstance($registry);

        register_scheduled_task('helper-task', 'list', '5m');

        $this->assertSame(300, $registry->get('helper-task')?->intervalSeconds);
    }
}
