<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Example;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: '|example:hidden')]
class HiddenExampleCommand extends Command
{
}
