<?php

namespace TheatreCMS\Tests\Fixtures\Scheduler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/**
 * An in-memory SQLite connection with the scheduled_task_runs table
 * (SQLite's form of migrations/20261001_create_scheduled_task_runs_table.sql).
 */
trait SchedulerSchema
{
    protected function createSchedulerConnection(): Connection
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            TestCase::markTestSkipped('PDO SQLite driver is not available; skipping.');
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE scheduled_task_runs (
                name VARCHAR(191) NOT NULL PRIMARY KEY,
                last_started_at DATETIME DEFAULT NULL,
                last_finished_at DATETIME DEFAULT NULL,
                last_status VARCHAR(16) DEFAULT NULL,
                last_exit_code INTEGER DEFAULT NULL,
                last_output TEXT DEFAULT NULL,
                updated_at DATETIME NOT NULL
            )'
        );

        return $connection;
    }
}
