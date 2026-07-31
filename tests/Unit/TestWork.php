<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\Person;
use TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

/**
 * Class TestWork
 * @package TheatreCMS\Tests\Unit
 *
 * @coversDefaultClass \TheatreCMS\Models\Work
 */
class TestWork extends TestCase
{
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