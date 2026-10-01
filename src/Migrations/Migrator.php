<?php

namespace TheatreCMS\Migrations;

use Doctrine\DBAL\Connection;
use TheatreCMS\Repositories\SchemaMigrationRepository;

/**
 * Applies pending `.sql` migrations from core and plugins, in filename order, recording each one in
 * `schema_migrations` once all of its statements have run. See documentation/console.md.
 */
class Migrator
{
    public function __construct(
        private readonly MigrationLocator $locator,
        private readonly SchemaMigrationRepository $applied,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return Migration[] migrations not yet applied, in the order they would run
     */
    public function pending(): array
    {
        $applied = $this->applied->all();

        return array_values(array_filter(
            $this->locator->all(),
            static fn(Migration $migration): bool => !isset($applied[SchemaMigrationRepository::key(
                $migration->source,
                $migration->filename,
            )]),
        ));
    }

    /**
     * @return Migration[] applied migrations whose file has changed since
     */
    public function changed(): array
    {
        $applied = $this->applied->all();

        return array_values(array_filter(
            $this->locator->all(),
            static function (Migration $migration) use ($applied): bool {
                $record = $applied[SchemaMigrationRepository::key($migration->source, $migration->filename)] ?? null;

                return $record !== null && $record->getChecksum() !== $migration->checksum();
            },
        ));
    }

    /**
     * True when nothing is recorded yet but the database already has tables, i.e. an install
     * whose schema predates the migrator. Running the baseline there would fail; use baseline().
     */
    public function needsBaseline(): bool
    {
        if ($this->applied->all() !== []) {
            return false;
        }

        $tables = array_diff(
            $this->connection->createSchemaManager()->listTableNames(),
            [$this->applied->tableName()],
        );

        return $tables !== [];
    }

    /**
     * @param callable(Migration): void|null $onApplied called after each migration is recorded
     * @return Migration[] the migrations applied
     *
     * @throws \RuntimeException when the database needs a baseline first
     * @throws MigrationFailedException when a statement fails; later migrations are not run
     */
    public function migrate(?callable $onApplied = null): array
    {
        if ($this->needsBaseline()) {
            throw new \RuntimeException(
                'This database already has tables but no recorded migrations. If its schema is current, '
                . 'run `bin/theatrecms migrate --baseline` once to record the existing migrations as applied.'
            );
        }

        $this->ensureTable();

        $applied = [];
        foreach ($this->pending() as $migration) {
            $this->run($migration);
            $this->applied->record($migration->source, $migration->filename, $migration->checksum());
            $applied[] = $migration;
            if ($onApplied !== null) {
                $onApplied($migration);
            }
        }

        return $applied;
    }

    /**
     * Records every pending migration as applied without running it, for a database whose schema
     * is already current.
     *
     * @return Migration[] the migrations recorded
     */
    public function baseline(): array
    {
        $this->ensureTable();

        $pending = $this->pending();
        foreach ($pending as $migration) {
            $this->applied->record($migration->source, $migration->filename, $migration->checksum(), true);
        }

        return $pending;
    }

    private function ensureTable(): void
    {
        if (!$this->applied->tableExists()) {
            $this->applied->createTable();
        }
    }

    private function run(Migration $migration): void
    {
        foreach (SqlSplitter::split($migration->sql()) as $index => $statement) {
            try {
                $this->connection->executeStatement($statement);
            } catch (\Throwable $e) {
                throw new MigrationFailedException($migration, $index + 1, $statement, $e);
            }
        }
    }
}
