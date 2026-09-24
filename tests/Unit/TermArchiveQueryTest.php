<?php

namespace TheatreCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Models\Term;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\WorkRepository;
use TheatreCMS\Taxonomy\TaxonomyDefinition;
use TheatreCMS\Taxonomy\TermArchiveQuery;

class TermArchiveQueryTest extends TestCase
{
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

        $posts = $this->createMock(PostRepository::class);
        $posts->expects($this->once())->method('fetchPublicByIds')->with([3, 4])->willReturn([$post]);

        $works = $this->createMock(WorkRepository::class);
        $works->expects($this->once())->method('fetchPublicByIds')->with([7])->willReturn([$work]);

        $query = new TermArchiveQuery($relationships, ['work' => $works, 'post' => $posts]);
        $taxonomy = new TaxonomyDefinition('audience', ['post', 'work'], 'Audiences', 'Audience');

        $this->assertSame(['post' => [$post], 'work' => [$work]], $query->itemsFor($term, $taxonomy));
    }

    public function testSkipsContentTypesWithNoRepository(): void
    {
        $term = new Term('audience', 'Family', 'family');

        $relationships = $this->createMock(TermRelationshipRepository::class);
        $relationships->expects($this->never())->method('contentIdsFor');

        $query = new TermArchiveQuery($relationships, []);
        $taxonomy = new TaxonomyDefinition('audience', ['production'], 'Audiences', 'Audience');

        $this->assertSame([], $query->itemsFor($term, $taxonomy));
    }
}
