<?php

namespace TheatreCMS\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheatreCMS\Services\MediaFilenameBackfillService;

#[AsCommand(
    name: 'media:rename-filenames',
    description: 'Rename generated media filenames to SEO-friendly slugs',
)]
class MediaRenameFilenamesCommand extends Command
{
    public function __construct(private readonly MediaFilenameBackfillService $filenames)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be renamed without renaming');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $changes = $this->filenames->renameToSeoSlugs($dryRun);

        $prefix = $dryRun ? '[dry run] Would rename' : 'Renamed';
        foreach ($changes as $change) {
            $output->writeln(sprintf('%s media #%d: %s -> %s', $prefix, $change['id'], $change['from'], $change['to']));
        }
        $output->writeln(sprintf('%s %d file(s) total.', $prefix, count($changes)));

        return Command::SUCCESS;
    }
}
