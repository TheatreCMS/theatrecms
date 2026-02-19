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
    public function testSetAndGetName(): void
    {
        $venue = new Venue();
        $name = 'The Grand Theatre';
        $venue->setName($name);
        $this->assertEquals($name, $venue->getName());
    }

    public function testSetAndGetAddress(): void
    {
        $venue = new Venue();
        $address = '123 Main Street';
        $venue->setAddress($address);
        $this->assertEquals($address, $venue->getAddress());
    }

    public function testSetAndGetCity(): void
    {
        $venue = new Venue();
        $city = 'Portland';
        $venue->setCity($city);
        $this->assertEquals($city, $venue->getCity());
    }

    public function testSetAndGetState(): void
    {
        $venue = new Venue();
        $state = 'Oregon';
        $venue->setState($state);
        $this->assertEquals($state, $venue->getState());
    }

    public function testSetAndGetPostcode(): void
    {
        $venue = new Venue();
        $postcode = '97201';
        $venue->setPostcode($postcode);
        $this->assertEquals($postcode, $venue->getPostcode());
    }

    public function testSetAndGetCountry(): void
    {
        $venue = new Venue();
        $country = 'USA';
        $venue->setCountry($country);
        $this->assertEquals($country, $venue->getCountry());
    }

    public function testSetAndGetCapacity(): void
    {
        $venue = new Venue();
        $capacity = 500;
        $venue->setCapacity($capacity);
        $this->assertEquals($capacity, $venue->getCapacity());
    }

    public function testCapacityCanBeNull(): void
    {
        $venue = new Venue();
        $venue->setCapacity(null);
        $this->assertNull($venue->getCapacity());
    }

    public function testSetAndGetDescription(): void
    {
        $venue = new Venue();
        $description = 'A historic theatre in the heart of downtown.';
        $venue->setDescription($description);
        $this->assertEquals($description, $venue->getDescription());
    }

    public function testDescriptionCanBeNull(): void
    {
        $venue = new Venue();
        $venue->setDescription(null);
        $this->assertNull($venue->getDescription());
    }

    public function testSetAndGetAccessibilityInfo(): void
    {
        $venue = new Venue();
        $accessibilityInfo = 'Wheelchair accessible with elevator access to all levels.';
        $venue->setAccessibilityInfo($accessibilityInfo);
        $this->assertEquals($accessibilityInfo, $venue->getAccessibilityInfo());
    }

    public function testAccessibilityInfoCanBeNull(): void
    {
        $venue = new Venue();
        $venue->setAccessibilityInfo(null);
        $this->assertNull($venue->getAccessibilityInfo());
    }

    public function testSetAndGetWebsiteUrl(): void
    {
        $venue = new Venue();
        $websiteUrl = 'https://grandtheatre.com';
        $venue->setWebsiteUrl($websiteUrl);
        $this->assertEquals($websiteUrl, $venue->getWebsiteUrl());
    }

    public function testWebsiteUrlCanBeNull(): void
    {
        $venue = new Venue();
        $venue->setWebsiteUrl(null);
        $this->assertNull($venue->getWebsiteUrl());
    }

    public function testSetAndGetMapUrl(): void
    {
        $venue = new Venue();
        $mapUrl = 'https://maps.google.com/place/grandtheatre';
        $venue->setMapUrl($mapUrl);
        $this->assertEquals($mapUrl, $venue->getMapUrl());
    }

    public function testMapUrlCanBeNull(): void
    {
        $venue = new Venue();
        $venue->setMapUrl(null);
        $this->assertNull($venue->getMapUrl());
    }

    public function testAllFieldsTogether(): void
    {
        $venue = new Venue();
        
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
