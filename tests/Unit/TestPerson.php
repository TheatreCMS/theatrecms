<?php
namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\Person;
use TheatreCMS\Tests\Includes\TestCase;

/**
 * Class TestPerson
 * @package TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass Person
 */
class TestPerson extends \PHPUnit\Framework\TestCase
{
    public function testConstructor(): void
    {
        $firstName = 'John';
        $lastName = 'Doe';
        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';
        $headshotUrl = 'https://example.com/headshots/johndoe.jpg';

        $person = new Person();
        $person
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setBiography($biography)
            ->setHeadshotUrl($headshotUrl);

        $this->assertEquals($firstName, $person->getFirstName());
        $this->assertEquals($lastName, $person->getLastName());
        $this->assertEquals($name, $person->getName());
        $this->assertEquals($biography, $person->getBiography());
        $this->assertEquals($headshotUrl, $person->getHeadshotUrl());
    }


    public function testSetBiography(): void
    {

        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';

        $person = new Person();
        $person->setBiography($biography);
        $this->assertEquals($biography, $person->getBiography());
    }

    public function testSetHeadshotUrl(): void
    {
        $headshotUrl = 'https://example.com/headshots/johndoe.jpg';

        $person = new Person();
        $person->setHeadshotUrl($headshotUrl);
        $this->assertEquals($headshotUrl, $person->getHeadshotUrl());
    }

    public function testJsonSerialize(): void
    {
        $firstName = 'John';
        $lastName = 'Doe';
        $name = 'John Doe';
        $biography = 'An accomplished actor known for his versatility.';
        $headshotUrl = 'https://example.com/headshots/johndoe.jpg';

        $person = new Person();
        $person
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setBiography($biography)
            ->setHeadshotUrl($headshotUrl);

        $jsonData = $person->jsonSerialize();

        $this->assertEquals($firstName, $jsonData['firstName']);
        $this->assertEquals($lastName, $jsonData['lastName']);
        $this->assertEquals($name, $jsonData['name']);
        $this->assertEquals($biography, $jsonData['biography']);
        $this->assertEquals($headshotUrl, $jsonData['headshotUrl']);
    }
}
