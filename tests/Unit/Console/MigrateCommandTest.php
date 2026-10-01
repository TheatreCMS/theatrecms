<?php

namespace TheatreCMS\Tests\Unit\Console;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TheatreCMS\Console\Command\MigrateCommand;
use TheatreCMS\Migrations\MigrationLocator;
use TheatreCMS\Migrations\Migrator;
use TheatreCMS\Plugin\PluginManager;
use TheatreCMS\Repositories\SchemaMigrationRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

class MigrateCommandTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private string $directory;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager(createSchema: false);
        $this->directory = sys_get_temp_dir() . '/theatrecms-migrate-command-test-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory . '/20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');

        $plugins = $this->createStub(PluginManager::class);
        $plugins->method('migrationPaths')->willReturn([]);
        $migrator = new Migrator(
            new MigrationLocator($this->directory, $plugins),
            new SchemaMigrationRepository($this->em),
            $this->em->getConnection(),
        );
        $this->tester = new CommandTester(new MigrateCommand($migrator));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
    }

    public function testDryRunListsPendingMigrations(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester->execute(['--dry-run' => true]));
        $this->assertSame(
            "Pending core/20261001_create_a.sql\n1 pending migration(s).\n",
            $this->tester->getDisplay(true),
        );
    }

    public function testMigratesThenHasNothingToDo(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester->execute([]));
        $this->assertSame(
            "Applied core/20261001_create_a.sql\nApplied 1 migration(s).\n",
            $this->tester->getDisplay(true),
        );

        $this->tester->execute([]);
        $this->assertSame("Nothing to migrate.\n", $this->tester->getDisplay(true));
    }

    public function testReportsAFailedMigration(): void
    {
        file_put_contents($this->directory . '/20261002_broken.sql', 'ALTER TABLE missing ADD COLUMN x INTEGER;');

        $this->assertSame(Command::FAILURE, $this->tester->execute([]));
        $display = $this->tester->getDisplay(true);
        $this->assertStringContainsString('Applied core/20261001_create_a.sql', $display);
        $this->assertStringContainsString('Migration core/20261002_broken.sql failed at statement 1', $display);
        $this->assertStringContainsString('ALTER TABLE missing ADD COLUMN x INTEGER', $display);
    }

    public function testRefusesAnExistingSchemaUntilBaselined(): void
    {
        $this->em->getConnection()->executeStatement('CREATE TABLE a (id INTEGER PRIMARY KEY)');

        $this->tester->execute(['--dry-run' => true]);
        $this->assertStringContainsString('it needs `migrate --baseline`', $this->tester->getDisplay());

        $this->assertSame(Command::FAILURE, $this->tester->execute([]));
        $this->assertStringContainsString('run `bin/theatrecms migrate --baseline` once', $this->tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $this->tester->execute(['--baseline' => true]));
        $this->assertSame(
            "Recorded core/20261001_create_a.sql as applied.\nBaselined 1 migration(s).\n",
            $this->tester->getDisplay(true),
        );

        $this->tester->execute([]);
        $this->assertSame("Nothing to migrate.\n", $this->tester->getDisplay(true));
    }

    public function testWarnsAboutEditedMigrations(): void
    {
        $this->tester->execute([]);
        file_put_contents($this->directory . '/20261001_create_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY, b TEXT);');

        $this->tester->execute([]);

        $this->assertSame(
            "Warning: core/20261001_create_a.sql has changed since it was applied.\nNothing to migrate.\n",
            $this->tester->getDisplay(true),
        );
    }
}
