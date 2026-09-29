<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see https://robo.li/
 */

use Robo\Tasks;

class RoboFile extends Tasks
{
    // define public methods as commands
    public function tests(): void
    {
        $this->taskPHPUnit()
            ->configFile('phpunit.xml.dist')
            ->run();
    }

    public function phpstan(): void
    {
        $this->taskExec('./vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G')->run();
    }
}
