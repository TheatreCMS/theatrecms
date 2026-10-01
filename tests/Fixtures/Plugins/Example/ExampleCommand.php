<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Example;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A plugin console command, built through the container so the greeter is injected.
 */
#[AsCommand(name: 'example:greet|example:hello', description: 'Print the example greeting')]
class ExampleCommand extends Command
{
    public function __construct(private readonly ExampleGreeter $greeter)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->greeter->greet());

        return Command::SUCCESS;
    }
}
