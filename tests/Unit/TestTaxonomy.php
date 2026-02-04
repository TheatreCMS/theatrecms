<?php
namespace Clubdeuce\TheatreCMS\Tests\Unit;

use Clubdeuce\TheatreCMS\Models\Taxonomy;
use PHPUnit\Framework\TestCase;

class TestTaxonomy extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $taxonomy = new Taxonomy();

        $this->assertEquals(0, $taxonomy->getId());
        
        $taxonomy->setSlug('test-slug');
        $this->assertEquals('test-slug', $taxonomy->getSlug());
        
        $taxonomy->setLabel('Test Label');
        $this->assertEquals('Test Label', $taxonomy->getLabel());
        
        $taxonomy->setDescription('Test Description');
        $this->assertEquals('Test Description', $taxonomy->getDescription());

        $taxonomy->setDescription(null);
        $this->assertEquals('', $taxonomy->getDescription());
    }
}
