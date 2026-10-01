<?php

namespace TheatreCMS\Migrations;

/**
 * A `.sql` migration file from core's `migrations/` or a plugin's migration directory.
 */
final class Migration
{
    public function __construct(
        public readonly string $source,
        public readonly string $filename,
        public readonly string $path,
    ) {
    }

    public function checksum(): string
    {
        return hash_file('sha256', $this->path) ?: '';
    }

    public function sql(): string
    {
        $sql = file_get_contents($this->path);
        if ($sql === false) {
            throw new \RuntimeException(sprintf('Cannot read migration %s.', $this->path));
        }

        return $sql;
    }

    public function label(): string
    {
        return $this->source . '/' . $this->filename;
    }
}
