<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\RoleType;
use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

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
}
