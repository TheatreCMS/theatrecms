<?php

namespace TheatreCMS\Console\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheatreCMS\Scheduler\ScheduledTaskRegistry;
use TheatreCMS\Scheduler\ScheduledTaskRunRepository;
use TheatreCMS\Scheduler\Scheduler;

#[AsCommand(name: 'schedule:list', description: 'List scheduled tasks and when they last and next run')]
class ScheduleListCommand extends Command
{
    public function __construct(
        private readonly ScheduledTaskRegistry $registry,
        private readonly ScheduledTaskRunRepository $runs,
        private readonly Scheduler $scheduler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tasks = $this->registry->all();
        if ($tasks === []) {
            $output->writeln('No scheduled tasks are registered.');
            return Command::SUCCESS;
        }

        $runs = $this->runs->all();
        $table = new Table($output);
        $table->setHeaders(['Task', 'Command', 'Every', 'Last run (UTC)', 'Status', 'Next due (UTC)']);

        foreach ($tasks as $name => $task) {
            $run = $runs[$name] ?? null;
            $nextDue = $this->scheduler->nextDueAt($task, $run);
            $table->addRow([
                $name,
                $task->command,
                $this->duration($task->intervalSeconds),
                $this->time($run?->lastStartedAt),
                $run->lastStatus ?? 'never run',
                $nextDue === null ? 'next schedule:run' : $this->time($nextDue),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function time(?DateTimeImmutable $time): string
    {
        return $time?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i') ?? '';
    }

    private function duration(int $seconds): string
    {
        foreach (['d' => 86400, 'h' => 3600, 'm' => 60] as $unit => $size) {
            if ($seconds % $size === 0) {
                return ($seconds / $size) . $unit;
            }
        }

        return $seconds . 's';
    }
}
