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
    public function tests()
    {
        $this->taskPHPUnit()
            ->configFile('phpunit.xml.dist')
            ->run();
    }
}