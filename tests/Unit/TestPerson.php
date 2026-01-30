<?php
namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Tests\Includes\TestCase;

/**
 * Class TestPerson
 * @package Clubdeuce\TheaterCMS\Tests\Unit
 *
 * @coversDefaultClass Person
 */
class TestPerson extends \PHPUnit\Framework\TestCase
{
    public function testConstructor(): void
    {
        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';
        $headshotUrl = 'https://example.com/headshots/johndoe.jpg';

        $person = new Person($name, $biography, $headshotUrl);
        $this->assertEquals($name, $person->getName());
        $this->assertEquals($biography, $person->getBiography());
        $this->assertEquals($headshotUrl, $person->getHeadshotUrl());
    }

    public function testSetName(): void
    {
        $person = new Person('Jane Doe');
        $newName = 'Jane Smith';
        $person->setName($newName);
        $this->assertEquals($newName, $person->getName());
    }

    public function testSetBiography(): void
    {

        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';

        $person = new Person($name, $biography);
        $newBiography = 'A talented actress with a passion for theater.';
        $person->setBiography($newBiography);
        $this->assertEquals($newBiography, $person->getBiography());
    }

    public function testSetHeadshotUrl(): void
    {
        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';
        $headshotUrl = 'https://example.com/headshots/johndoe.jpg';

        $person = new Person($name, $biography, $headshotUrl);
        $newHeadshotUrl = 'https://example.com/new_headshot.jpg';
        $person->setHeadshotUrl($newHeadshotUrl);
        $this->assertEquals($newHeadshotUrl, $person->getHeadshotUrl());
    }

    public function testJsonSerialize(): void
    {
        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';
        $headshotUrl = 'https://example.com/headshots/johndoe.jpg';

        $person = new Person($name, $biography, $headshotUrl);
        $jsonData = $person->jsonSerialize();

        $this->assertEquals($name, $jsonData['name']);
        $this->assertEquals($biography, $jsonData['biography']);
        $this->assertEquals($headshotUrl, $jsonData['headshotUrl']);
    }
}
