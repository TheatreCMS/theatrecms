-- Last-run state of each task run by `bin/theatrecms schedule:run` (see documentation/console.md),
-- mapped by src/Models/ScheduledTaskRun.php.
CREATE TABLE IF NOT EXISTS scheduled_task_runs (
    name VARCHAR(191) NOT NULL,
    last_started_at DATETIME NULL,
    last_finished_at DATETIME NULL,
    last_status VARCHAR(16) NULL,
    last_exit_code INT NULL,
    last_output LONGTEXT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
