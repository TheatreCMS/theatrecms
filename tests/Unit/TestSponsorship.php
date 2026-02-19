<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Season;
use Clubdeuce\TheatreCMS\Models\Sponsor;
use Clubdeuce\TheatreCMS\Models\Sponsorship;
use Clubdeuce\TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

class TestSponsorship extends TestCase
{
    private Sponsor $sponsor;
    private Sponsorship $sponsorship;
    private Season $season;
    private Production $production;
    private Work $work;

    protected function setUp(): void
    {
        $this->sponsor = new Sponsor();
        $this->sponsorship = new Sponsorship($this->sponsor);
        $this->season = new Season('2026-2027', '2026 Season');
        $this->work = new Work();
        $this->production = new Production('Hamlet', $this->season, $this->work);
    }

    public function testConstructor(): void
    {
        $this->assertSame($this->sponsor, $this->sponsorship->getSponsor());
        $this->assertNull($this->sponsorship->getSeason());
        $this->assertNull($this->sponsorship->getProduction());
    }

    public function testSetSponsor(): void
    {
        $sponsor2 = new Sponsor();
        $this->sponsorship->setSponsor($sponsor2);

        $this->assertSame($sponsor2, $this->sponsorship->getSponsor());
    }

    public function testSetSeason(): void
    {
        $this->sponsorship->setSeason($this->season);

        $this->assertSame($this->season, $this->sponsorship->getSeason());
    }

    public function testSetProduction(): void
    {
        $this->sponsorship->setProduction($this->production);

        $this->assertSame($this->production, $this->sponsorship->getProduction());
    }

    public function testSponsorSponsorships(): void
    {
        $this->sponsor->addSponsorship($this->sponsorship);

        $this->assertCount(1, $this->sponsor->getSponsorships());
        $this->assertSame($this->sponsorship, $this->sponsor->getSponsorships()->first());
    }

    public function testSeasonSponsorships(): void
    {
        $this->sponsorship->setSeason($this->season);
        $this->season->addSponsorship($this->sponsorship);

        $this->assertCount(1, $this->season->getSponsorships());
        $this->assertSame($this->sponsorship, $this->season->getSponsorships()->first());
    }

    public function testProductionSponsorships(): void
    {
        $this->sponsorship->setProduction($this->production);
        $this->production->addSponsorship($this->sponsorship);

        $this->assertCount(1, $this->production->getSponsorships());
        $this->assertSame($this->sponsorship, $this->production->getSponsorships()->first());
    }
}
