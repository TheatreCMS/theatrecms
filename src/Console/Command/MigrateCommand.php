<?php

namespace TheatreCMS\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheatreCMS\Migrations\Migration;
use TheatreCMS\Migrations\MigrationFailedException;
use TheatreCMS\Migrations\Migrator;

#[AsCommand(name: 'migrate', description: 'Apply pending database migrations from core and plugins')]
class MigrateCommand extends Command
{
    public function __construct(private readonly Migrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List pending migrations without applying them')
            ->addOption(
                'baseline',
                null,
                InputOption::VALUE_NONE,
                'Record the baseline snapshot as applied without running it (once, for a database created before migrate); then run migrate',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        foreach ($this->migrator->changed() as $migration) {
            $errors->writeln("<comment>Warning: {$migration->label()} has changed since it was applied.</comment>");
        }

        if ($input->getOption('dry-run')) {
            return $this->dryRun($output);
        }

        if ($input->getOption('baseline')) {
            try {
                $recorded = $this->migrator->baseline();
            } catch (\RuntimeException $e) {
                $errors->writeln("<error>{$e->getMessage()}</error>");

                return Command::FAILURE;
            }
            foreach ($recorded as $migration) {
                $output->writeln("Recorded {$migration->label()} as applied.");
            }
            $output->writeln(sprintf('Baselined %d migration(s). Run `bin/theatrecms migrate` to apply later ones.', count($recorded)));

            return Command::SUCCESS;
        }

        try {
            $applied = $this->migrator->migrate(static function (Migration $migration) use ($output): void {
                $output->writeln("Applied {$migration->label()}");
            });
        } catch (MigrationFailedException $e) {
            $errors->writeln("<error>{$e->getMessage()}</error>");
            $errors->writeln('Statement:');
            $errors->writeln($e->statement, OutputInterface::OUTPUT_RAW);
            $errors->writeln('Earlier statements in this file may have taken effect. Fix the database or the file, then re-run.');

            return Command::FAILURE;
        } catch (\RuntimeException $e) {
            $errors->writeln("<error>{$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        $output->writeln($applied === [] ? 'Nothing to migrate.' : sprintf('Applied %d migration(s).', count($applied)));

        return Command::SUCCESS;
    }

    private function dryRun(OutputInterface $output): int
    {
        $pending = $this->migrator->pending();
        if ($pending === []) {
            $output->writeln('Nothing to migrate.');
            return Command::SUCCESS;
        }

        if ($this->migrator->needsBaseline()) {
            $output->writeln('<comment>This database has tables but no recorded migrations; it needs `migrate --baseline`.</comment>');
        }

        foreach ($pending as $migration) {
            $output->writeln("Pending {$migration->label()}");
        }
        $output->writeln(sprintf('%d pending migration(s).', count($pending)));

        return Command::SUCCESS;
    }
}
