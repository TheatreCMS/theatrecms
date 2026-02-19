<?php
namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Venue;
use PHPUnit\Framework\TestCase;

/**
 * Class TestVenue
 * @package Clubdeuce\TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass \Clubdeuce\TheatreCMS\Models\Venue
 */
class TestVenue extends TestCase
{
    private function makeVenue(): Venue
    {
        return new Venue(
            'The Grand Theatre',
            '123 Main Street',
            'Portland',
            'Oregon',
            '97201',
            'USA'
        );
    }

    public function testSetAndGetName(): void
    {
        $venue = $this->makeVenue();
        $name = 'The Grand Theatre';
        $venue->setName($name);
        $this->assertEquals($name, $venue->getName());
    }

    public function testSetAndGetAddress(): void
    {
        $venue = $this->makeVenue();
        $address = '123 Main Street';
        $venue->setAddress($address);
        $this->assertEquals($address, $venue->getAddress());
    }

    public function testSetAndGetCity(): void
    {
        $venue = $this->makeVenue();
        $city = 'Portland';
        $venue->setCity($city);
        $this->assertEquals($city, $venue->getCity());
    }

    public function testSetAndGetState(): void
    {
        $venue = $this->makeVenue();
        $state = 'Oregon';
        $venue->setState($state);
        $this->assertEquals($state, $venue->getState());
    }

    public function testSetAndGetPostcode(): void
    {
        $venue = $this->makeVenue();
        $postcode = '97201';
        $venue->setPostcode($postcode);
        $this->assertEquals($postcode, $venue->getPostcode());
    }

    public function testSetAndGetCountry(): void
    {
        $venue = $this->makeVenue();
        $country = 'USA';
        $venue->setCountry($country);
        $this->assertEquals($country, $venue->getCountry());
    }

    public function testSetAndGetCapacity(): void
    {
        $venue = $this->makeVenue();
        $capacity = 500;
        $venue->setCapacity($capacity);
        $this->assertEquals($capacity, $venue->getCapacity());
    }

    public function testCapacityCanBeNull(): void
    {
        $venue = $this->makeVenue();
        $venue->setCapacity(null);
        $this->assertNull($venue->getCapacity());
    }

    public function testSetAndGetDescription(): void
    {
        $venue = $this->makeVenue();
        $description = 'A historic theatre in the heart of downtown.';
        $venue->setDescription($description);
        $this->assertEquals($description, $venue->getDescription());
    }

    public function testDescriptionCanBeNull(): void
    {
        $venue = $this->makeVenue();
        $venue->setDescription(null);
        $this->assertNull($venue->getDescription());
    }

    public function testSetAndGetAccessibilityInfo(): void
    {
        $venue = $this->makeVenue();
        $accessibilityInfo = 'Wheelchair accessible with elevator access to all levels.';
        $venue->setAccessibilityInfo($accessibilityInfo);
        $this->assertEquals($accessibilityInfo, $venue->getAccessibilityInfo());
    }

    public function testAccessibilityInfoCanBeNull(): void
    {
        $venue = $this->makeVenue();
        $venue->setAccessibilityInfo(null);
        $this->assertNull($venue->getAccessibilityInfo());
    }

    public function testSetAndGetWebsiteUrl(): void
    {
        $venue = $this->makeVenue();
        $websiteUrl = 'https://grandtheatre.com';
        $venue->setWebsiteUrl($websiteUrl);
        $this->assertEquals($websiteUrl, $venue->getWebsiteUrl());
    }

    public function testWebsiteUrlCanBeNull(): void
    {
        $venue = $this->makeVenue();
        $venue->setWebsiteUrl(null);
        $this->assertNull($venue->getWebsiteUrl());
    }

    public function testSetAndGetMapUrl(): void
    {
        $venue = $this->makeVenue();
        $mapUrl = 'https://maps.google.com/place/grandtheatre';
        $venue->setMapUrl($mapUrl);
        $this->assertEquals($mapUrl, $venue->getMapUrl());
    }

    public function testMapUrlCanBeNull(): void
    {
        $venue = $this->makeVenue();
        $venue->setMapUrl(null);
        $this->assertNull($venue->getMapUrl());
    }

    public function testAllFieldsTogether(): void
    {
        $venue = $this->makeVenue();

        $venue->setName('The Grand Theatre')
              ->setAddress('123 Main Street')
              ->setCity('Portland')
              ->setState('Oregon')
              ->setPostcode('97201')
              ->setCountry('USA')
              ->setCapacity(500)
              ->setDescription('A historic theatre in the heart of downtown.')
              ->setAccessibilityInfo('Wheelchair accessible with elevator access to all levels.')
              ->setWebsiteUrl('https://grandtheatre.com')
              ->setMapUrl('https://maps.google.com/place/grandtheatre');

        $this->assertEquals('The Grand Theatre', $venue->getName());
        $this->assertEquals('123 Main Street', $venue->getAddress());
        $this->assertEquals('Portland', $venue->getCity());
        $this->assertEquals('Oregon', $venue->getState());
        $this->assertEquals('97201', $venue->getPostcode());
        $this->assertEquals('USA', $venue->getCountry());
        $this->assertEquals(500, $venue->getCapacity());
        $this->assertEquals('A historic theatre in the heart of downtown.', $venue->getDescription());
        $this->assertEquals('Wheelchair accessible with elevator access to all levels.', $venue->getAccessibilityInfo());
        $this->assertEquals('https://grandtheatre.com', $venue->getWebsiteUrl());
        $this->assertEquals('https://maps.google.com/place/grandtheatre', $venue->getMapUrl());
    }
}
