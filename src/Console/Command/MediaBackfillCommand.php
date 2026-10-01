<?php

namespace TheatreCMS\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheatreCMS\Services\ImageBackfillService;

#[AsCommand(
    name: 'media:backfill',
    description: 'Register files in www/uploads/ as media and repoint content at them',
)]
class MediaBackfillCommand extends Command
{
    public function __construct(private readonly ImageBackfillService $backfill)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without changing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        $created = $this->backfill->scanUploads($dryRun);
        $repointed = $this->backfill->repointAll($dryRun);

        $output->writeln(sprintf(
            '%s %d media row(s) from www/uploads/.',
            $dryRun ? '[dry run] Would create' : 'Created',
            $created,
        ));

        $prefix = $dryRun ? '[dry run] Would repoint' : 'Repointed';
        foreach ($repointed as $table => $count) {
            $output->writeln(sprintf('%s %d %s row(s) to their matching image.', $prefix, $count, $table));
        }

        return Command::SUCCESS;
    }
}
