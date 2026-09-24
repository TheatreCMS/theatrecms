<?php

namespace TheatreCMS\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Auth\Capability;
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

    public function testCapabilityDefaultsToManageOptionsAndCanBeOverridden(): void
    {
        $registry = new TaxonomyRegistry();

        $this->assertSame(Capability::MANAGE_OPTIONS, $registry->register('audience', ['work'])->capability);
        $this->assertSame(
            Capability::EDIT_POSTS,
            $registry->register('post_category', ['post'], ['capability' => Capability::EDIT_POSTS])->capability
        );
    }

    public function testUrlPrefixDefaultsToNameWithHyphensAndCanBeOverridden(): void
    {
        $registry = new TaxonomyRegistry();

        $this->assertSame('post-category', $registry->register('post_category', ['post'])->urlPrefix);
        $this->assertSame('genres', $registry->register('genre', ['work'], ['url_prefix' => 'genres'])->urlPrefix);
    }

    public function testHasArchiveDefaultsTrueAndCanBeDisabled(): void
    {
        $registry = new TaxonomyRegistry();

        $this->assertTrue($registry->register('genre', ['work'])->hasArchive);
        $this->assertFalse($registry->register('audience', ['work'], ['has_archive' => false])->hasArchive);
    }

    #[DataProvider('invalidUrlPrefixProvider')]
    public function testRegisterRejectsInvalidUrlPrefix(string $prefix): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaxonomyRegistry())->register('genre', ['work'], ['url_prefix' => $prefix]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrlPrefixProvider(): array
    {
        return [
            'empty' => [''],
            'slash' => ['works/genre'],
            'uppercase' => ['Genre'],
            'reserved admin' => ['admin'],
        ];
    }

    public function testRegisterRejectsUrlPrefixUsedByAnotherTaxonomy(): void
    {
        $registry = new TaxonomyRegistry();
        $registry->register('post_category', ['post'], ['url_prefix' => 'category']);

        $this->expectException(InvalidArgumentException::class);
        $registry->register('work_category', ['work'], ['url_prefix' => 'category']);
    }

    public function testReRegisteringATaxonomyMayKeepItsUrlPrefix(): void
    {
        $registry = new TaxonomyRegistry();
        $registry->register('genre', ['work']);

        $this->assertSame('genre', $registry->register('genre', ['work', 'production'])->urlPrefix);
    }

    public function testFindByUrlPrefix(): void
    {
        $registry = new TaxonomyRegistry();
        $registry->register('post_category', ['post'], ['url_prefix' => 'category']);

        $this->assertSame('post_category', $registry->findByUrlPrefix('category')?->name);
        $this->assertNull($registry->findByUrlPrefix('post-category'));
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
