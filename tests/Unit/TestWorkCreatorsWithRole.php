<?php

namespace TheatreCMS\Tests\Unit;

use TheatreCMS\Models\Person;
use TheatreCMS\Models\Work;
use PHPUnit\Framework\TestCase;

class TestWorkCreatorsWithRole extends TestCase
{
    public function testAddCreatorWithRole()
    {
        $work = new Work();

        $person = $this->createStub(Person::class);
        $person->method('getId')->willReturn(12345);

        $work->addCreator($person, 'Author');

        $workCreators = $work->getWorkCreators();
        $this->assertCount(1, $workCreators);

        $wc = $workCreators[0];
        $this->assertEquals('Author', $wc->role());
        $this->assertSame($person, $wc->person());
    }
}
