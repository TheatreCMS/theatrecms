<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Event;
use Clubdeuce\TheatreCMS\Models\Production;
use Clubdeuce\TheatreCMS\Models\Season;
use PHPUnit\Framework\TestCase;

/**
 * Class TestEvent
 * @package Clubdeuce\TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass  \Clubdeuce\TheatreCMS\Models\Event
 */
class TestEvent extends TestCase
{
    private Production $production;
    private \DateTimeImmutable $startsAt;

    protected function setUp(): void
    {
        $season = new Season('2026-2027', '2026-2027 Season');
        $this->production = new Production('Hamlet', $season);
        $this->startsAt = new \DateTimeImmutable('2026-10-15 20:00:00');
    }

    public function testConstructor()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');

        $this->assertEquals($this->production, $event->getProduction());
        $this->assertEquals($this->startsAt, $event->getStartsAt());
        $this->assertEquals('scheduled', $event->getStatus());
        $this->assertNull($event->getEndsAt());
        $this->assertNull($event->getTicketUrl());
        $this->assertNull($event->getNotes());
    }

    public function testSetProduction()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');
        $season2 = new Season('2027-2028', '2027-2028 Season');
        $newProduction = new Production('Macbeth', $season2);
        $event->setProduction($newProduction);

        $this->assertEquals($newProduction, $event->getProduction());
    }

    public function testSetStartsAt()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');
        $newStartsAt = new \DateTimeImmutable('2026-10-16 19:00:00');
        $event->setStartsAt($newStartsAt);

        $this->assertEquals($newStartsAt, $event->getStartsAt());
    }

    public function testSetEndsAt()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');
        $endsAt = new \DateTimeImmutable('2026-10-15 22:30:00');
        $event->setEndsAt($endsAt);

        $this->assertEquals($endsAt, $event->getEndsAt());
    }

    public function testSetStatus()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');
        $event->setStatus('sold_out');

        $this->assertEquals('sold_out', $event->getStatus());
    }

    public function testSetTicketUrl()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');
        $ticketUrl = 'https://tickets.example.com/event/12345';
        $event->setTicketUrl($ticketUrl);

        $this->assertEquals($ticketUrl, $event->getTicketUrl());
    }

    public function testSetNotes()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');
        $notes = 'Post-show discussion with the director';
        $event->setNotes($notes);

        $this->assertEquals($notes, $event->getNotes());
    }

    public function testMultipleStatusValues()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');

        $event->setStatus('cancelled');
        $this->assertEquals('cancelled', $event->getStatus());

        $event->setStatus('sold_out');
        $this->assertEquals('sold_out', $event->getStatus());
    }

    public function testNullableFields()
    {
        $event = new Event($this->production, $this->startsAt, 'scheduled');

        $event->setEndsAt(null);
        $this->assertNull($event->getEndsAt());

        $event->setTicketUrl(null);
        $this->assertNull($event->getTicketUrl());

        $event->setNotes(null);
        $this->assertNull($event->getNotes());
    }

    public function testEventWithAllFields()
    {
        $startsAt = new \DateTimeImmutable('2026-10-15 20:00:00');
        $endsAt = new \DateTimeImmutable('2026-10-15 22:30:00');
        $ticketUrl = 'https://tickets.example.com/event/12345';
        $notes = 'Sensory-friendly performance';

        $event = new Event($this->production, $startsAt, 'scheduled');
        $event->setEndsAt($endsAt);
        $event->setTicketUrl($ticketUrl);
        $event->setNotes($notes);

        $this->assertEquals($this->production, $event->getProduction());
        $this->assertEquals($startsAt, $event->getStartsAt());
        $this->assertEquals($endsAt, $event->getEndsAt());
        $this->assertEquals('scheduled', $event->getStatus());
        $this->assertEquals($ticketUrl, $event->getTicketUrl());
        $this->assertEquals($notes, $event->getNotes());
    }
}
