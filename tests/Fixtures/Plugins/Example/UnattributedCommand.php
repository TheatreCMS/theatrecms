<?php

namespace TheatreCMS\Tests\Fixtures\Plugins\Example;

use Symfony\Component\Console\Command\Command;

/**
 * Missing #[AsCommand], so the console skips it.
 */
class UnattributedCommand extends Command
{
}
