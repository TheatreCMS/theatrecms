<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\ProductionPerson;
use Clubdeuce\TheatreCMS\Models\RoleType;
use Clubdeuce\TheatreCMS\Models\Season;
use PHPUnit\Framework\TestCase;

/**
 * Class TestProductionPerson
 * @package Clubdeuce\TheaterCMS\Tests\Unit
 *
 * @coversDefaultClass ProductionPerson
 */
class TestProductionPerson extends TestCase
{
    public function testConstructor(): void
    {
        $season = new Season('2024', '2024 Season');
        $production = new Production('Hamlet', $season);
        $person = new Person();
        $person->setFirstName('John')->setLastName('Doe');

        $productionPerson = new ProductionPerson($production, $person);

        $this->assertEquals($production, $productionPerson->getProduction());
        $this->assertEquals($person, $productionPerson->getPerson());
        $this->assertNull($productionPerson->getRoleType());
    }

    public function testSetRoleType(): void
    {
        $season = new Season('2024', '2024 Season');
        $production = new Production('Hamlet', $season);
        $person = new Person();
        $person->setFirstName('Jane')->setLastName('Smith');

        $productionPerson = new ProductionPerson($production, $person);
        $productionPerson->setRoleType(RoleType::Cast);

        $this->assertEquals(RoleType::Cast, $productionPerson->getRoleType());
    }

    public function testSetRoleTypeToNull(): void
    {
        $season = new Season('2024', '2024 Season');
        $production = new Production('Hamlet', $season);
        $person = new Person();
        $person->setFirstName('Bob')->setLastName('Jones');

        $productionPerson = new ProductionPerson($production, $person);
        $productionPerson->setRoleType(RoleType::ProductionTeam);
        $productionPerson->setRoleType(null);

        $this->assertNull($productionPerson->getRoleType());
    }

    public function testAllRoleTypes(): void
    {
        $season = new Season('2024', '2024 Season');
        $production = new Production('Hamlet', $season);
        $person = new Person();
        $person->setFirstName('Alice')->setLastName('Brown');

        $productionPerson = new ProductionPerson($production, $person);

        // Test Cast
        $productionPerson->setRoleType(RoleType::Cast);
        $this->assertEquals(RoleType::Cast, $productionPerson->getRoleType());
        $this->assertEquals('cast', $productionPerson->getRoleType()->value);

        // Test ProductionTeam
        $productionPerson->setRoleType(RoleType::ProductionTeam);
        $this->assertEquals(RoleType::ProductionTeam, $productionPerson->getRoleType());
        $this->assertEquals('production_team', $productionPerson->getRoleType()->value);

        // Test Orchestra
        $productionPerson->setRoleType(RoleType::Orchestra);
        $this->assertEquals(RoleType::Orchestra, $productionPerson->getRoleType());
        $this->assertEquals('orchestra', $productionPerson->getRoleType()->value);

        // Test Creative
        $productionPerson->setRoleType(RoleType::Creative);
        $this->assertEquals(RoleType::Creative, $productionPerson->getRoleType());
        $this->assertEquals('creative', $productionPerson->getRoleType()->value);
    }

    public function testSetRole(): void
    {
        $season = new Season('2024', '2024 Season');
        $production = new Production('Hamlet', $season);
        $person = new Person();
        $person->setFirstName('Tom')->setLastName('Hardy');

        $productionPerson = new ProductionPerson($production, $person);
        $productionPerson->setRoleType(RoleType::Cast);
        $productionPerson->setRole('Prince Hamlet');

        $this->assertEquals('Prince Hamlet', $productionPerson->getRole());
        $this->assertEquals(RoleType::Cast, $productionPerson->getRoleType());
    }
}
