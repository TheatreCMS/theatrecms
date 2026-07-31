<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\Event;
use TheatreCMS\Models\Production;
use TheatreCMS\Models\Season;
use TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

/**
 * Class TestEvent
 * @package TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass  \TheatreCMS\Models\Event
 */
class TestEvent extends TestCase
{
    private Work $work;
    private Production $production;
    private \DateTimeImmutable $startsAt;
    private Event $event;

    protected function setUp(): void
    {
        $this->work = new Work();
        $season = new Season('2026-2027', '2026-2027 Season');
        $this->production = new Production('Hamlet', $season, $this->work);
        $this->startsAt = new \DateTimeImmutable('2026-10-15 20:00:00');
        $this->event = new Event($this->startsAt, 'scheduled', $this->production);
    }

    public function testConstructor()
    {
        $this->assertEquals($this->production, $this->event->getProduction());
        $this->assertEquals($this->startsAt, $this->event->getStartsAt());
        $this->assertEquals('scheduled', $this->event->getStatus());
        $this->assertNull($this->event->getEndsAt());
        $this->assertNull($this->event->getTicketUrl());
        $this->assertNull($this->event->getNotes());
        $this->assertNull($this->event->getTitle());
    }

    public function testSetProduction()
    {
        $work = new Work();
        $season2 = new Season('2027-2028', '2027-2028 Season');
        $newProduction = new Production('Macbeth', $season2, $work);
        $this->event->setProduction($newProduction);

        $this->assertEquals($newProduction, $this->event->getProduction());
    }

    public function testSetStartsAt()
    {
        $newStartsAt = new \DateTimeImmutable('2026-10-16 19:00:00');
        $this->event->setStartsAt($newStartsAt);

        $this->assertEquals($newStartsAt, $this->event->getStartsAt());
    }

    public function testSetEndsAt()
    {
        $endsAt = new \DateTimeImmutable('2026-10-15 22:30:00');
        $this->event->setEndsAt($endsAt);

        $this->assertEquals($endsAt, $this->event->getEndsAt());
    }

    public function testSetStatus()
    {
        $this->event->setStatus('sold_out');

        $this->assertEquals('sold_out', $this->event->getStatus());
    }

    public function testSetTicketUrl()
    {
        $ticketUrl = 'https://tickets.example.com/event/12345';
        $this->event->setTicketUrl($ticketUrl);

        $this->assertEquals($ticketUrl, $this->event->getTicketUrl());
    }

    public function testSetNotes()
    {
        $notes = 'Post-show discussion with the director';
        $this->event->setNotes($notes);

        $this->assertEquals($notes, $this->event->getNotes());
    }

    public function testSetTitle()
    {
        $title = 'Opening Night';
        $this->event->setTitle($title);

        $this->assertEquals($title, $this->event->getTitle());
    }

    public function testMultipleStatusValues()
    {
        $this->event->setStatus('cancelled');
        $this->assertEquals('cancelled', $this->event->getStatus());

        $this->event->setStatus('sold_out');
        $this->assertEquals('sold_out', $this->event->getStatus());
    }

    public function testNullableFields()
    {
        $this->event->setEndsAt(null);
        $this->assertNull($this->event->getEndsAt());

        $this->event->setTicketUrl(null);
        $this->assertNull($this->event->getTicketUrl());

        $this->event->setNotes(null);
        $this->assertNull($this->event->getNotes());

        $this->event->setTitle(null);
        $this->assertNull($this->event->getTitle());
    }

    public function testEventWithAllFields()
    {
        $startsAt = new \DateTimeImmutable('2026-10-15 20:00:00');
        $endsAt = new \DateTimeImmutable('2026-10-15 22:30:00');
        $ticketUrl = 'https://tickets.example.com/event/12345';
        $notes = 'Sensory-friendly performance';

        $event = new Event($startsAt, 'scheduled', $this->production);
        $event->setEndsAt($endsAt);
        $event->setTicketUrl($ticketUrl);
        $event->setNotes($notes);
        $event->setTitle('Opening Night');

        $this->assertEquals($this->production, $event->getProduction());
        $this->assertEquals($startsAt, $event->getStartsAt());
        $this->assertEquals($endsAt, $event->getEndsAt());
        $this->assertEquals('scheduled', $event->getStatus());
        $this->assertEquals($ticketUrl, $event->getTicketUrl());
        $this->assertEquals($notes, $event->getNotes());
        $this->assertEquals('Opening Night', $event->getTitle());
    }

    public function testConstructorWithoutProduction()
    {
        $startsAt = new \DateTimeImmutable('2026-11-20 19:00:00');
        $event = new Event($startsAt, 'scheduled');

        $this->assertNull($event->getProduction());
        $this->assertEquals($startsAt, $event->getStartsAt());
        $this->assertEquals('scheduled', $event->getStatus());
    }

    public function testStandaloneEventLikeGala()
    {
        $startsAt = new \DateTimeImmutable('2026-12-15 18:00:00');
        $endsAt = new \DateTimeImmutable('2026-12-15 22:00:00');
        $galaEvent = new Event($startsAt, 'scheduled');
        $galaEvent->setEndsAt($endsAt);
        $galaEvent->setTicketUrl('https://tickets.example.com/gala');
        $galaEvent->setNotes('Annual fundraising gala');

        $this->assertNull($galaEvent->getProduction());
        $this->assertEquals($startsAt, $galaEvent->getStartsAt());
        $this->assertEquals($endsAt, $galaEvent->getEndsAt());
        $this->assertEquals('scheduled', $galaEvent->getStatus());
        $this->assertEquals('https://tickets.example.com/gala', $galaEvent->getTicketUrl());
        $this->assertEquals('Annual fundraising gala', $galaEvent->getNotes());
    }

    public function testSetProductionToNull()
    {
        $this->assertEquals($this->production, $this->event->getProduction());

        $this->event->setProduction(null);
        $this->assertNull($this->event->getProduction());
    }

    public function testSetProductionFromNullToProduction()
    {
        $event = new Event($this->startsAt, 'scheduled');
        $this->assertNull($event->getProduction());

        $event->setProduction($this->production);
        $this->assertEquals($this->production, $event->getProduction());
    }
}
