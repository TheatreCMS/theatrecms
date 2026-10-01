<?php

namespace TheatreCMS\Repositories;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use TheatreCMS\Models\ScheduledTaskRun;

/**
 * Last-run state of each scheduled task. Deliberately does not extend BaseRepository: runs are
 * keyed by task name and are never listed, searched or paginated in the admin.
 */
class ScheduledTaskRunRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $name): ?ScheduledTaskRun
    {
        return $this->em->find(ScheduledTaskRun::class, $name);
    }

    /**
     * @return array<string, ScheduledTaskRun> name => run
     */
    public function all(): array
    {
        $runs = [];
        foreach ($this->em->getRepository(ScheduledTaskRun::class)->findAll() as $run) {
            $runs[$run->getName()] = $run;
        }

        return $runs;
    }

    public function markStarted(string $name, DateTimeImmutable $at): void
    {
        $this->findOrCreate($name)->markStarted($at);
        $this->em->flush();
    }

    public function markFinished(string $name, bool $succeeded, ?int $exitCode, string $output, DateTimeImmutable $at): void
    {
        $this->findOrCreate($name)->markFinished($succeeded, $exitCode, $output, $at);
        $this->em->flush();
    }

    private function findOrCreate(string $name): ScheduledTaskRun
    {
        $run = $this->find($name);
        if ($run === null) {
            $run = new ScheduledTaskRun($name);
            $this->em->persist($run);
        }

        return $run;
    }
}
