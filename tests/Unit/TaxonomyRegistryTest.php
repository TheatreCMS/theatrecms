<?php

namespace TheatreCMS\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Taxonomy\TaxonomyRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Taxonomy\TaxonomyRegistry
 */
class TaxonomyRegistryTest extends TestCase
{
    public function testRegisterStoresDefinitionWithArgs(): void
    {
        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work'], ['label' => 'Genres', 'singular_label' => 'Genre', 'multiple' => false]);

        $genre = $registry->get('genre');

        $this->assertNotNull($genre);
        $this->assertSame(['work'], $genre->contentTypes);
        $this->assertSame('Genres', $genre->label);
        $this->assertSame('Genre', $genre->singularLabel);
        $this->assertFalse($genre->multiple);
    }

    public function testLabelsDefaultFromNameAndMultipleDefaultsTrue(): void
    {
        $registry = new TaxonomyRegistry();
        $definition = $registry->register('post_category', ['post']);

        $this->assertSame('Post Category', $definition->label);
        $this->assertTrue($definition->multiple);
    }

    public function testForContentTypeReturnsOnlyApplicableTaxonomies(): void
    {
        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work']);
        $registry->register('post_category', ['post']);
        $registry->register('audience', ['work', 'production']);

        $this->assertSame(['genre', 'audience'], array_keys($registry->forContentType('work')));
        $this->assertSame([], $registry->forContentType('venue'));
    }

    public function testRequireFailsClosedForUnknownTaxonomy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry())->require('genre');
    }

    public function testRegisterRejectsInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry())->register('Bad Name', ['work']);
    }

    public function testRegisterRejectsEmptyContentTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry())->register('genre', []);
    }
}
