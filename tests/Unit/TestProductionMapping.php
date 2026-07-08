<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\RoleType;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;

class TestProductionMapping extends TestCase
{
    public function testAddToCreativeTeam(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $work = new Work();
        $production = new Production('Test Production', $season, $work);
        $person = new Person();

        $production->addToCreativeTeam($person, 'Director');

        $this->assertCount(1, $production->getPeople());
        $this->assertCount(1, $production->getCreativeTeam());
        $this->assertCount(0, $production->getPerformers());

        $productionPerson = $production->getPeople()->first();
        $this->assertSame($production, $productionPerson->getProduction());
        $this->assertSame($person, $productionPerson->getPerson());
        $this->assertSame(RoleType::Creative, $productionPerson->getRoleType());
        $this->assertSame('Director', $productionPerson->getRole());
    }

    public function testAddPerformer(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $work = new Work();
        $production = new Production('Test Production', $season, $work);
        $person = new Person();

        $production->addPerformer($person, 'Hamlet');

        $this->assertCount(1, $production->getPeople());
        $this->assertCount(0, $production->getCreativeTeam());
        $this->assertCount(1, $production->getPerformers());

        $productionPerson = $production->getPeople()->first();
        $this->assertSame($production, $productionPerson->getProduction());
        $this->assertSame($person, $productionPerson->getPerson());
        $this->assertSame(RoleType::Cast, $productionPerson->getRoleType());
        $this->assertSame('Hamlet', $productionPerson->getRole());
    }

    public function testSetWorksReplacesExistingWorkSelectionWithoutDuplicates(): void
    {
        $season = new Season('2025-2026', '2025-2026 Season');
        $originalWork = new Work();
        $replacementWork = new Work();
        $production = new Production('Test Production', $season, $originalWork);

        $production->setWorks([$replacementWork, $replacementWork]);

        $this->assertCount(1, $production->getWorks());
        $this->assertFalse($production->getWorks()->contains($originalWork));
        $this->assertTrue($production->getWorks()->contains($replacementWork));
    }
}
