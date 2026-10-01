<?php

namespace TheatreCMS\Models;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * A migration file that `bin/theatrecms migrate` has applied (or marked applied with --baseline).
 * The migrator creates this table itself, so it is not in the baseline migration.
 */
#[Entity]
#[Table(name: 'schema_migrations')]
class SchemaMigration
{
    /** Source of core's own migrations; plugin migrations use the plugin's package name. */
    public const SOURCE_CORE = 'core';

    #[Id, Column(type: 'string', length: 191)]
    private string $source;

    #[Id, Column(type: 'string', length: 191)]
    private string $filename;

    /** SHA-256 of the file as applied, to spot files edited afterwards. */
    #[Column(type: 'string', length: 64)]
    private string $checksum;

    #[Column(name: 'applied_at', type: 'datetime_immutable')]
    private DateTimeImmutable $appliedAt;

    /** True when recorded by --baseline rather than executed. */
    #[Column(type: 'boolean', options: ['default' => false])]
    private bool $baselined;

    public function __construct(string $source, string $filename, string $checksum, bool $baselined = false)
    {
        $this->source = $source;
        $this->filename = $filename;
        $this->checksum = $checksum;
        $this->baselined = $baselined;
        $this->appliedAt = new DateTimeImmutable();
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getAppliedAt(): DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function isBaselined(): bool
    {
        return $this->baselined;
    }
}
