<?php

namespace TheatreCMS\Migrations;

/**
 * A statement in a migration failed. The migration is not recorded as applied; statements before
 * the failing one may already have taken effect, since MySQL commits DDL immediately.
 */
class MigrationFailedException extends \RuntimeException
{
    public function __construct(
        public readonly Migration $migration,
        public readonly int $statementNumber,
        public readonly string $statement,
        \Throwable $previous,
    ) {
        parent::__construct(sprintf(
            'Migration %s failed at statement %d: %s',
            $migration->label(),
            $statementNumber,
            $previous->getMessage(),
        ), 0, $previous);
    }
}
