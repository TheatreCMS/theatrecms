<?php

namespace TheatreCMS\Tests\Unit\Scheduler;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Scheduler\TaskLocker;

class TaskLockerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/theatrecms-locker-test-' . bin2hex(random_bytes(4)) . '/locks';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
            rmdir(dirname($this->directory));
        }
    }

    public function testALockIsExclusiveUntilReleased(): void
    {
        $locker = new TaskLocker($this->directory);

        $first = $locker->acquire('tessitura:sync');
        $this->assertNotNull($first, 'creates the directory and takes the lock');
        $this->assertNull($locker->acquire('tessitura:sync'));
        $this->assertNotNull($other = $locker->acquire('other'), 'locks are per task');

        $locker->release($first);
        $locker->release($other);

        $this->assertNotNull($again = $locker->acquire('tessitura:sync'));
        $locker->release($again);
        $this->assertFileExists($this->directory . '/schedule-tessitura_sync.lock');
    }
}
