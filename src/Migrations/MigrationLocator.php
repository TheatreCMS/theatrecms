<?php

namespace TheatreCMS\Migrations;

use TheatreCMS\Models\SchemaMigration;
use TheatreCMS\Plugin\PluginManager;

/**
 * Finds the `*.sql` files in core's migrations directory and in each plugin's migrationPaths().
 * Subdirectories (such as core's `migrations/legacy/`) are not searched.
 */
class MigrationLocator
{
    public function __construct(
        private readonly string $coreDirectory,
        private readonly PluginManager $plugins,
    ) {
    }

    /**
     * Every migration, ordered by filename (so by timestamp) across core and plugins; on equal
     * filenames core's comes first, then plugins by package name.
     *
     * @return Migration[]
     */
    public function all(): array
    {
        $migrations = $this->inDirectory(SchemaMigration::SOURCE_CORE, $this->coreDirectory);

        foreach ($this->plugins->migrationPaths() as $package => $directories) {
            foreach ($directories as $directory) {
                array_push($migrations, ...$this->inDirectory($package, $directory));
            }
        }

        usort($migrations, static function (Migration $a, Migration $b): int {
            return [$a->filename, $a->source !== SchemaMigration::SOURCE_CORE, $a->source]
                <=> [$b->filename, $b->source !== SchemaMigration::SOURCE_CORE, $b->source];
        });

        return $migrations;
    }

    /**
     * @return Migration[]
     */
    private function inDirectory(string $source, string $directory): array
    {
        $migrations = [];
        foreach (glob(rtrim($directory, '/') . '/*.sql') ?: [] as $path) {
            $migrations[] = new Migration($source, basename($path), $path);
        }

        return $migrations;
    }
}
