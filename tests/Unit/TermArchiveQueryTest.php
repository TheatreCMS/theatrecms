<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Controllers\SponsorController;
use TheatreCMS\Controllers\WorksController;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TermArchiveQuery;

class TermArchiveQueryTest extends TestCase
{
    private ContentTypeRegistry $contentTypes;

    protected function setUp(): void
    {
        $this->contentTypes = new ContentTypeRegistry();
        $this->contentTypes->register('work', ['controller' => WorksController::class]);
        $this->contentTypes->register('sponsor', ['controller' => SponsorController::class, 'public' => false]);
        $this->contentTypes->register('post');
        $this->contentTypes->register('press_release');
    }

    public function testGroupsVisibleItemsByContentTypeInTheTaxonomysOrder(): void
    {
        $term = new Term('audience', 'Family', 'family');
        $work = new \stdClass();
        $post = new \stdClass();

        $relationships = $this->createStub(TermRelationshipRepository::class);
        $relationships->method('contentIdsFor')->willReturnMap([
            [$term, 'post', [3, 4]],
            [$term, 'work', [7]],
        ]);

        $entries = $this->entries('post');
        $entries->expects($this->once())->method('fetchPublicByIds')->with([3, 4])->willReturn([$post]);

        $works = $this->createMock(WorkRepository::class);
        $works->expects($this->once())->method('fetchPublicByIds')->with([7])->willReturn([$work]);

        $query = new TermArchiveQuery($relationships, $this->contentTypes, $entries, [
            'work' => $works,
        ]);
        $taxonomy = new TaxonomyDefinition('audience', ['post', 'work'], 'Audiences', 'Audience');

        $this->assertSame(['post' => [$post], 'work' => [$work]], $query->itemsFor($term, $taxonomy));
    }

    public function testResolvesARegisteredEntryTypeThroughTheEntryRepository(): void
    {
        $term = new Term('audience', 'Family', 'family');
        $release = new \stdClass();

        $relationships = $this->createStub(TermRelationshipRepository::class);
        $relationships->method('contentIdsFor')->willReturn([5]);

        $entries = $this->entries('press_release');
        $entries->expects($this->once())->method('fetchPublicByIds')->with([5])->willReturn([$release]);

        $query = new TermArchiveQuery($relationships, $this->contentTypes, $entries, []);
        $taxonomy = new TaxonomyDefinition('audience', ['press_release'], 'Audiences', 'Audience');

        $this->assertSame(['press_release' => [$release]], $query->itemsFor($term, $taxonomy));
    }

    public function testSkipsANonPublicTypeEvenWhenItHasARepository(): void
    {
        $relationships = $this->createMock(TermRelationshipRepository::class);
        $relationships->expects($this->never())->method('contentIdsFor');

        $query = new TermArchiveQuery($relationships, $this->contentTypes, $this->createStub(EntryRepository::class), [
            'sponsor' => $this->createStub(WorkRepository::class),
        ]);
        $taxonomy = new TaxonomyDefinition('audience', ['sponsor'], 'Audiences', 'Audience');

        $this->assertSame([], $query->itemsFor(new Term('audience', 'Family', 'family'), $taxonomy));
    }

    public function testSkipsAnUnregisteredType(): void
    {
        $relationships = $this->createMock(TermRelationshipRepository::class);
        $relationships->expects($this->never())->method('contentIdsFor');

        $entries = $this->createStub(EntryRepository::class);
        $query = new TermArchiveQuery($relationships, $this->contentTypes, $entries, []);
        $taxonomy = new TaxonomyDefinition('audience', ['concert'], 'Audiences', 'Audience');

        $this->assertSame([], $query->itemsFor(new Term('audience', 'Family', 'family'), $taxonomy));
    }

    public function testSkipsAModelBackedTypeWithNoRepository(): void
    {
        $relationships = $this->createMock(TermRelationshipRepository::class);
        $relationships->expects($this->never())->method('contentIdsFor');

        $entries = $this->createStub(EntryRepository::class);
        $query = new TermArchiveQuery($relationships, $this->contentTypes, $entries, []);
        $taxonomy = new TaxonomyDefinition('audience', ['work'], 'Audiences', 'Audience');

        $this->assertSame([], $query->itemsFor(new Term('audience', 'Family', 'family'), $taxonomy));
    }

    /**
     * An EntryRepository that must be scoped to $type once. forType() returns `static`, so the
     * double hands back itself and the test sets its fetchPublicByIds() expectation on it.
     */
    private function entries(string $type): EntryRepository&MockObject
    {
        $entries = $this->createMock(EntryRepository::class);
        $entries->expects($this->once())->method('forType')->with($type)->willReturnSelf();

        return $entries;
    }
}
