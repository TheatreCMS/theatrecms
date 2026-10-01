<?php

namespace TheatreCMS\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheatreCMS\Scheduler\ScheduledTask;
use TheatreCMS\Scheduler\Scheduler;
use TheatreCMS\Scheduler\TaskResult;

/**
 * Meant to be run by cron every minute; see documentation/console.md.
 */
#[AsCommand(name: 'schedule:run', description: 'Run the scheduled tasks that are due')]
class ScheduleRunCommand extends Command
{
    public function __construct(private readonly Scheduler $scheduler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = false;

        $outcomes = $this->scheduler->runDue(
            function (ScheduledTask $task, string $outcome, ?TaskResult $result) use ($output, &$failed): void {
                switch ($outcome) {
                    case Scheduler::OUTCOME_SUCCESS:
                        $output->writeln("<info>✔</info> {$task->name}: {$task->command}");
                        break;
                    case Scheduler::OUTCOME_FAILED:
                        $failed = true;
                        $output->writeln(sprintf(
                            '<error>✘</error> %s: %s (exit code %s)',
                            $task->name,
                            $task->command,
                            $result->exitCode ?? 'none',
                        ));
                        break;
                    case Scheduler::OUTCOME_LOCKED:
                        $output->writeln("- {$task->name}: still running from an earlier run; skipped");
                        break;
                    default:
                        $output->writeln("- {$task->name}: not due", OutputInterface::VERBOSITY_VERBOSE);
                }
            },
        );

        if ($outcomes === []) {
            $output->writeln('No scheduled tasks are registered.', OutputInterface::VERBOSITY_VERBOSE);
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
