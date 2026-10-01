<?php

namespace TheatreCMS\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheatreCMS\Services\MediaVariantBackfillService;

#[AsCommand(
    name: 'media:regenerate-thumbnails',
    description: 'Generate missing image-size variants (all variants with --force)',
)]
class MediaRegenerateThumbnailsCommand extends Command
{
    public function __construct(private readonly MediaVariantBackfillService $variants)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be generated without writing files')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Regenerate variants that already exist')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Only this registered image size');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $size = $input->getOption('size');

        $counts = $this->variants->regenerate($dryRun, (bool) $input->getOption('force'), $size !== null ? (string) $size : null);

        $prefix = $dryRun ? '[dry run] Would generate' : 'Generated';
        foreach ($counts as $sizeName => $count) {
            $output->writeln(sprintf("%s %d '%s' thumbnail(s).", $prefix, $count, $sizeName));
        }

        return Command::SUCCESS;
    }
}
