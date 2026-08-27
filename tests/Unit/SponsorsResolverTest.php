<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Sponsor;
use TheatreCMS\Models\Sponsorship;
use TheatreCMS\Theme\SponsorsResolver;
use TheatreCMS\Twig\SponsorsExtension;

class SponsorsResolverTest extends TestCase
{
    public function testResolvesCommaSeparatedSponsorNamesFromEntity(): void
    {
        $season = new Season('a-season', 'A Season');

        $sponsorA = (new Sponsor())->setName('Acme Corp');
        $sponsorB = (new Sponsor())->setName('Globex Inc');

        $season->addSponsorship(new Sponsorship($sponsorA));
        $season->addSponsorship(new Sponsorship($sponsorB));

        $this->assertSame('Acme Corp, Globex Inc', (new SponsorsResolver())->resolve($season));
    }

    public function testResolvesEmptyStringWhenEntityHasNoSponsors(): void
    {
        $season = new Season('a-season', 'A Season');

        $this->assertSame('', (new SponsorsResolver())->resolve($season));
    }

    public function testThrowsWhenEntityCannotResolveSponsors(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SponsorsResolver())->resolve(new \stdClass());
    }

    public function testExtensionReturnsCommaSeparatedString(): void
    {
        $season = new Season('a-season', 'A Season');
        $season->addSponsorship(new Sponsorship((new Sponsor())->setName('Acme Corp')));

        $result = (new SponsorsExtension(new SponsorsResolver()))->theSponsors($season);

        $this->assertSame('Acme Corp', $result);
    }
}
