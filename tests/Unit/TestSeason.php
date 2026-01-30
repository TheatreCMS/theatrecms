<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Season;
use PHPUnit\Framework\TestCase;

/**
 * Class TestSeason
 * @package Clubdeuce\TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass  \Clubdeuce\TheatreCMS\Models\Season
 */
class TestSeason extends TestCase
{
    public function testConstructor()
    {
        $season = new Season('2026-2027', '2026-2027 Season');

        $this->assertEquals('2026-2027', $season->getSlug());
        $this->assertEquals('2026-2027 Season', $season->getLabel());
        $this->assertEquals('', $season->getOverview());
        $this->assertNull($season->getStartDate());
    }

    public function testSetSlug()
    {
        $season = new Season('2026-2027', '2026-2027 Season');
        $season->setSlug('2027-2028');

        $this->assertEquals('2027-2028', $season->getSlug());
    }

    public function testSetLabel()
    {
        $season = new Season('2026-2027', '2026-2027 Season');
        $season->setLabel('2027-2028 Season');

        $this->assertEquals('2027-2028 Season', $season->getLabel());
    }

    public function testSetOverview()
    {
        $season = new Season('2026-2027', '2026-2027 Season');
        $season->setOverview('This is the overview for the 2026-2027 season.');

        $this->assertEquals('This is the overview for the 2026-2027 season.', $season->getOverview());
    }

    public function testSetStartDate()
    {
        $season = new Season('2026-2027', '2026-2027 Season');
        $startDate = new \DateTime('2026-09-01');
        $season->setStartDate($startDate);

        $this->assertEquals($startDate, $season->getStartDate());
    }

    public function testJsonSerialize()
    {
        $season = new Season('2026-2027', '2026-2027 Season');
        $season->setOverview('This is the overview for the 2026-2027 season.');
        $startDate = new \DateTime('2026-09-01');
        $season->setStartDate($startDate);

        $expected = [
            'id' => 0,
            'slug' => '2026-2027',
            'label' => '2026-2027 Season',
            'overview' => 'This is the overview for the 2026-2027 season.',
            'startDate' => $startDate->format('Y-m-d'),
        ];

        $this->assertEquals($expected, $season->jsonSerialize());
    }
}