<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

/**
 * Class TestWork
 * @package Clubdeuce\TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass \Clubdeuce\TheatreCMS\Models\Work
 */
class TestWork extends TestCase
{
    public function testId()
    {
        $work = new Work();

        $work->setId(12345);

        $this->assertEquals(12345, $work->getId());
    }

    public function testTitle()
    {
        $work = new Work();
        $work->setTitle('Test Work');

        $this->assertEquals('Test Work', $work->getTitle());
    }

    public function testDescription()
    {
        $work = new Work();
        $work->setDescription('This is a test description.');

        $this->assertEquals('This is a test description.', $work->getDescription());
    }

    public function testCreators()
    {
        $work = new Work();
        $creator = $this->createMock(Person::class);
        $creator->expects($this->once())->method('getId')->willReturn(22789);

        $work->addCreator($creator);

        $this->assertTrue($work->getCreators()->contains($creator));
        $creators = $work->getCreators();
        $this->assertCount(1, $creators);
        $this->assertEquals(22789, $creators[0]->getId());
    }
}