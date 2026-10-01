<?php

namespace TheatreCMS\Repositories;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use TheatreCMS\Models\SchemaMigration;

/**
 * Applied migrations. Deliberately does not extend BaseRepository: rows are keyed by
 * (source, filename) and never listed in the admin.
 */
class SchemaMigrationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function tableExists(): bool
    {
        return $this->em->getConnection()->createSchemaManager()->tablesExist([$this->tableName()]);
    }

    /**
     * Creates the schema_migrations table, which is how the migrator bootstraps itself on an
     * empty database.
     */
    public function createTable(): void
    {
        (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(SchemaMigration::class)]);
    }

    public function tableName(): string
    {
        return $this->em->getClassMetadata(SchemaMigration::class)->getTableName();
    }

    /**
     * @return array<string, SchemaMigration> "source/filename" => migration
     */
    public function all(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $applied = [];
        foreach ($this->em->getRepository(SchemaMigration::class)->findAll() as $migration) {
            $applied[self::key($migration->getSource(), $migration->getFilename())] = $migration;
        }

        return $applied;
    }

    public function record(string $source, string $filename, string $checksum, bool $baselined = false): void
    {
        $this->em->persist(new SchemaMigration($source, $filename, $checksum, $baselined));
        $this->em->flush();
    }

    public static function key(string $source, string $filename): string
    {
        return $source . '/' . $filename;
    }
}
