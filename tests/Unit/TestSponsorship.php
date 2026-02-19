<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Sponsor;
use Clubdeuce\TheatreCMS\Models\Sponsorship;
use PHPUnit\Framework\TestCase;

class TestSponsorship extends TestCase
{
    public function testConstructor()
    {
        $sponsor = new Sponsor();
        $sponsorship = new Sponsorship($sponsor);

        $this->assertSame($sponsor, $sponsorship->getSponsor());
        $this->assertNull($sponsorship->getSeason());
        $this->assertNull($sponsorship->getProduction());
    }

    public function testSetSponsor()
    {
        $sponsor1 = new Sponsor();
        $sponsor2 = new Sponsor();
        $sponsorship = new Sponsorship($sponsor1);
        $sponsorship->setSponsor($sponsor2);

        $this->assertSame($sponsor2, $sponsorship->getSponsor());
    }

    public function testSetSeason()
    {
        $sponsor = new Sponsor();
        $season = new Season('2026-2027', '2026 Season');
        $sponsorship = new Sponsorship($sponsor);
        $sponsorship->setSeason($season);

        $this->assertSame($season, $sponsorship->getSeason());
    }

    public function testSetProduction()
    {
        $sponsor = new Sponsor();
        $season = new Season('2026-2027', '2026 Season');
        $production = new Production('Hamlet', $season);
        $sponsorship = new Sponsorship($sponsor);
        $sponsorship->setProduction($production);

        $this->assertSame($production, $sponsorship->getProduction());
    }

    public function testSponsorSponsorships()
    {
        $sponsor = new Sponsor();
        $sponsorship = new Sponsorship($sponsor);
        $sponsor->addSponsorship($sponsorship);

        $this->assertCount(1, $sponsor->getSponsorships());
        $this->assertSame($sponsorship, $sponsor->getSponsorships()->first());
    }

    public function testSeasonSponsorships()
    {
        $sponsor = new Sponsor();
        $season = new Season('2026-2027', '2026 Season');
        $sponsorship = new Sponsorship($sponsor);
        $sponsorship->setSeason($season);
        $season->addSponsorship($sponsorship);

        $this->assertCount(1, $season->getSponsorships());
        $this->assertSame($sponsorship, $season->getSponsorships()->first());
    }

    public function testProductionSponsorships()
    {
        $sponsor = new Sponsor();
        $season = new Season('2026-2027', '2026 Season');
        $production = new Production('Hamlet', $season);
        $sponsorship = new Sponsorship($sponsor);
        $sponsorship->setProduction($production);
        $production->addSponsorship($sponsorship);

        $this->assertCount(1, $production->getSponsorships());
        $this->assertSame($sponsorship, $production->getSponsorships()->first());
    }
}
