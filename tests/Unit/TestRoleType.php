<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\RoleType;
use PHPUnit\Framework\TestCase;

/**
 * Class TestRoleType
 * @package Clubdeuce\TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass RoleType
 */
class TestRoleType extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('cast', RoleType::Cast->value);
        $this->assertEquals('production_team', RoleType::ProductionTeam->value);
        $this->assertEquals('orchestra', RoleType::Orchestra->value);
        $this->assertEquals('creative', RoleType::Creative->value);
    }

    public function testEnumCases(): void
    {
        $cases = RoleType::cases();
        $this->assertCount(4, $cases);
        $this->assertContains(RoleType::Cast, $cases);
        $this->assertContains(RoleType::ProductionTeam, $cases);
        $this->assertContains(RoleType::Orchestra, $cases);
        $this->assertContains(RoleType::Creative, $cases);
    }

    public function testFromValue(): void
    {
        $this->assertEquals(RoleType::Cast, RoleType::from('cast'));
        $this->assertEquals(RoleType::ProductionTeam, RoleType::from('production_team'));
        $this->assertEquals(RoleType::Orchestra, RoleType::from('orchestra'));
        $this->assertEquals(RoleType::Creative, RoleType::from('creative'));
    }
}
