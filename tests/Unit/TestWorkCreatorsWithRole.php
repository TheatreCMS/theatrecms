<?php

namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Person;
use Clubdeuce\TheatreCMS\Models\Work;
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
