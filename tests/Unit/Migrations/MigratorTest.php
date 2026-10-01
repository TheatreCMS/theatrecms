<?php

namespace TheatreCMS\Tests\Unit\Migrations;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Migrations\Migration;
use TheatreCMS\Migrations\MigrationFailedException;
use TheatreCMS\Migrations\MigrationLocator;
use TheatreCMS\Migrations\Migrator;
use TheatreCMS\Plugin\DirectoryPluginDiscovery;
use TheatreCMS\Plugin\PluginManager;
use TheatreCMS\Repositories\SchemaMigrationRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

class MigratorTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private string $coreDirectory;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager(createSchema: false);
        $this->coreDirectory = sys_get_temp_dir() . '/theatrecms-migrator-test-' . bin2hex(random_bytes(4));
        mkdir($this->coreDirectory . '/legacy', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->coreDirectory . '/{,legacy/}*.sql', GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->coreDirectory . '/legacy');
        rmdir($this->coreDirectory);
    }

    public function testAppliesPendingMigrationsInFilenameOrderOnce(): void
    {
        $this->file('20261002_add_b.sql', 'ALTER TABLE a ADD COLUMN b INTEGER;');
        $this->file('20261001_create_a.sql', "CREATE TABLE a (id INTEGER PRIMARY KEY);\nINSERT INTO a (id) VALUES (1);");
        $this->file('legacy/20250101_old.sql', 'THIS IS NOT RUN;');
        $migrator = $this->migrator();

        $seen = [];
        $applied = $migrator->migrate(static function (Migration $migration) use (&$seen): void {
            $seen[] = $migration->filename;
        });

        $this->assertSame(['20261001_create_a.sql', '20261002_add_b.sql'], $this->filenames($applied));
        $this->assertSame(['20261001_create_a.sql', '20261002_add_b.sql'], $seen);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM a WHERE b IS NULL'));
        $this->assertSame([], $migrator->migrate(), 'a second run has nothing to do');
        $this->assertSame([], $migrator->pending());

        $recorded = (new SchemaMigrationRepository($this->em))->all();
        $this->assertSame(['core/20261001_create_a.sql', 'core/20261002_add_b.sql'], array_keys($recorded));
        $this->assertFalse($recorded['core/20261001_create_a.sql']->isBaselined());
        $this->assertSame(
            hash_file('sha256', $this->coreDirectory . '/20261001_create_a.sql'),
            $recorded['core/20261001_create_a.sql']->getChecksum(),
        );
    }

    public function testAppliesLaterMigrationsAddedAfterARun(): void
    {
        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');
        $migrator = $this->migrator();
        $migrator->migrate();

        $this->file('20261005_create_c.sql', 'CREATE TABLE c (id INTEGER PRIMARY KEY);');

        $this->assertSame(['20261005_create_c.sql'], $this->filenames($migrator->pending()));
        $this->assertSame(['20261005_create_c.sql'], $this->filenames($migrator->migrate()));
    }

    public function testAFailingMigrationStopsTheRunAndIsNotRecorded(): void
    {
        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');
        $this->file('20261002_broken.sql', "CREATE TABLE b (id INTEGER);\nALTER TABLE missing ADD COLUMN x INTEGER;");
        $this->file('20261003_create_c.sql', 'CREATE TABLE c (id INTEGER PRIMARY KEY);');
        $migrator = $this->migrator();

        try {
            $migrator->migrate();
            $this->fail('Expected the broken migration to fail.');
        } catch (MigrationFailedException $e) {
            $this->assertSame('20261002_broken.sql', $e->migration->filename);
            $this->assertSame(2, $e->statementNumber);
            $this->assertSame('ALTER TABLE missing ADD COLUMN x INTEGER', $e->statement);
        }

        $this->assertSame(['20261002_broken.sql', '20261003_create_c.sql'], $this->filenames($migrator->pending()));
        $this->assertFalse($this->em->getConnection()->createSchemaManager()->tablesExist(['c']));
    }

    public function testAnExistingSchemaNeedsABaselineFirst(): void
    {
        $this->em->getConnection()->executeStatement('CREATE TABLE a (id INTEGER PRIMARY KEY)');
        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');
        $this->file('20261002_create_b.sql', 'CREATE TABLE b (id INTEGER PRIMARY KEY);');
        $migrator = $this->migrator();

        $this->assertTrue($migrator->needsBaseline());
        try {
            $migrator->migrate();
            $this->fail('Expected migrate() to refuse an unbaselined database.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('migrate --baseline', $e->getMessage());
        }

        $this->assertSame(['20261001_create_a.sql', '20261002_create_b.sql'], $this->filenames($migrator->baseline()));
        $this->assertFalse($migrator->needsBaseline());
        $this->assertSame([], $migrator->migrate(), 'baseline followed by migrate is a no-op');
        $this->assertFalse($this->em->getConnection()->createSchemaManager()->tablesExist(['b']), 'baselined files are not run');
        $this->assertTrue((new SchemaMigrationRepository($this->em))->all()['core/20261002_create_b.sql']->isBaselined());
    }

    public function testListingPendingMigrationsDoesNotCreateTheTable(): void
    {
        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');

        $this->assertSame(['20261001_create_a.sql'], $this->filenames($this->migrator()->pending()));
        $this->assertFalse((new SchemaMigrationRepository($this->em))->tableExists());
    }

    public function testReportsMigrationsEditedAfterTheyWereApplied(): void
    {
        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');
        $migrator = $this->migrator();
        $migrator->migrate();
        $this->assertSame([], $migrator->changed());

        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY, extra TEXT);');

        $this->assertSame(['20261001_create_a.sql'], $this->filenames($migrator->changed()));
        $this->assertSame([], $migrator->pending(), 'an edited file is not re-run');
    }

    public function testAppliesAPluginsMigrationsAlongsideCore(): void
    {
        $this->file('20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');
        $plugins = new PluginManager(new DirectoryPluginDiscovery(dirname(__DIR__, 2) . '/Fixtures/PluginsDir'));
        $plugins->discover();

        $applied = $this->migrator($plugins)->migrate();

        $this->assertSame(
            ['core/20261001_create_a.sql', 'theatrecms/example-plugin/20261002_create_examples_table.sql'],
            array_map(static fn(Migration $migration): string => $migration->label(), $applied),
        );
        $this->assertTrue($this->em->getConnection()->createSchemaManager()->tablesExist(['examples']));
    }

    private function migrator(?PluginManager $plugins = null): Migrator
    {
        if ($plugins === null) {
            $plugins = $this->createStub(PluginManager::class);
            $plugins->method('migrationPaths')->willReturn([]);
        }

        return new Migrator(
            new MigrationLocator($this->coreDirectory, $plugins),
            new SchemaMigrationRepository($this->em),
            $this->em->getConnection(),
        );
    }

    private function file(string $name, string $sql): void
    {
        file_put_contents($this->coreDirectory . '/' . $name, $sql);
    }

    /**
     * @param Migration[] $migrations
     * @return string[]
     */
    private function filenames(array $migrations): array
    {
        return array_map(static fn(Migration $migration): string => $migration->filename, $migrations);
    }
}
