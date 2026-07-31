<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\Person;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\ProductionPerson;
use TheatreCMS\Models\RoleType;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

/**
 * Class TestProductionPerson
 * @package TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass ProductionPerson
 */
class TestProductionPerson extends TestCase
{
    private Season $season;
    private Work $work;
    private Production $production;
    private Person $person;
    private ProductionPerson $productionPerson;

    protected function setUp(): void
    {
        $this->season = new Season('2024', '2024 Season');
        $this->work = (new Work())->setTitle('Hamlet');
        $this->production = new Production('Hamlet', $this->season, $this->work);
        $this->person = (new Person())->setFirstName('John')->setLastName('Doe');
        $this->productionPerson = new ProductionPerson($this->production, $this->person);
    }

    public function testConstructor(): void
    {
        $this->assertEquals($this->production, $this->productionPerson->getProduction());
        $this->assertEquals($this->person, $this->productionPerson->getPerson());
        $this->assertNull($this->productionPerson->getRoleType());
    }

    public function testSetRoleType(): void
    {
        $this->person->setFirstName('Jane')->setLastName('Smith');
        $this->productionPerson->setRoleType(RoleType::Cast);

        $this->assertEquals(RoleType::Cast, $this->productionPerson->getRoleType());
    }

    public function testSetRoleTypeToNull(): void
    {
        $this->person->setFirstName('Bob')->setLastName('Jones');

        $this->productionPerson->setRoleType(RoleType::ProductionTeam);
        $this->productionPerson->setRoleType(null);

        $this->assertNull($this->productionPerson->getRoleType());
    }

    public function testAllRoleTypes(): void
    {
        $this->person->setFirstName('Alice')->setLastName('Brown');

        // Test Cast
        $this->productionPerson->setRoleType(RoleType::Cast);
        $this->assertEquals(RoleType::Cast, $this->productionPerson->getRoleType());
        $this->assertEquals('cast', $this->productionPerson->getRoleType()->value);

        // Test ProductionTeam
        $this->productionPerson->setRoleType(RoleType::ProductionTeam);
        $this->assertEquals(RoleType::ProductionTeam, $this->productionPerson->getRoleType());
        $this->assertEquals('production_team', $this->productionPerson->getRoleType()->value);

        // Test Orchestra
        $this->productionPerson->setRoleType(RoleType::Orchestra);
        $this->assertEquals(RoleType::Orchestra, $this->productionPerson->getRoleType());
        $this->assertEquals('orchestra', $this->productionPerson->getRoleType()->value);

        // Test Creative
        $this->productionPerson->setRoleType(RoleType::Creative);
        $this->assertEquals(RoleType::Creative, $this->productionPerson->getRoleType());
        $this->assertEquals('creative', $this->productionPerson->getRoleType()->value);
    }

    public function testSetRole(): void
    {
        $this->person->setFirstName('Tom')->setLastName('Hardy');

        $this->productionPerson->setRoleType(RoleType::Cast);
        $this->productionPerson->setRole('Prince Hamlet');

        $this->assertEquals('Prince Hamlet', $this->productionPerson->getRole());
        $this->assertEquals(RoleType::Cast, $this->productionPerson->getRoleType());
    }
}
