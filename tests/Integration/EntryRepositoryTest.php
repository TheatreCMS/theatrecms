<?php

declare(strict_types=1);

namespace TheatreCMS\Tests\Integration;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Entry;
use TheatreCMS\Models\TermRelationship;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

/**
 * @coversDefaultClass \TheatreCMS\Repositories\EntryRepository
 */
class EntryRepositoryTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private EntryRepository $entries;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->entries = new EntryRepository($this->em);
    }

    public function testCreateStoresTheBoundType(): void
    {
        $entry = $this->entries->forType('press_release')->create([
            'title' => 'Season Announced',
            'content' => '{"blocks":[]}',
            'excerpt' => 'Our new season',
            'status' => 'published',
        ]);

        $this->assertGreaterThan(0, $entry->getId());
        $this->assertSame('press_release', $entry->getContentType());
        $this->assertSame('season-announced', $entry->getSlug());
        $this->assertSame('{"blocks":[]}', $entry->getContent());
        $this->assertSame('Our new season', $entry->getExcerpt());
        $this->assertSame(ContentStatus::PUBLISHED, $entry->getStatus());
    }

    public function testCreateDefaultsToDraft(): void
    {
        $entry = $this->entries->forType('press_release')->create(['title' => 'Draft']);

        $this->assertSame(ContentStatus::DRAFT, $entry->getStatus());
    }

    public function testCreateRequiresATitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->entries->forType('press_release')->create([]);
    }

    public function testCreateRejectsAnInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->entries->forType('press_release')->create(['title' => 'Launch', 'status' => 'archived']);
    }

    public function testForTypeLeavesTheOriginalUnbound(): void
    {
        $this->entries->forType('press_release');

        $this->expectException(LogicException::class);
        $this->entries->fetchAll();
    }

    /**
     * @return array<string, array{callable(EntryRepository): mixed}>
     */
    public static function unboundCalls(): array
    {
        return [
            'create' => [static fn (EntryRepository $r) => $r->create(['title' => 'Launch'])],
            'query' => [static fn (EntryRepository $r) => $r->query()],
            'fetchAll' => [static fn (EntryRepository $r) => $r->fetchAll()],
            'fetchPage' => [static fn (EntryRepository $r) => $r->fetchPage()],
            'fetchPublicByIds' => [static fn (EntryRepository $r) => $r->fetchPublicByIds([1])],
            'fetch' => [static fn (EntryRepository $r) => $r->fetch(1)],
            'fetchBySlug' => [static fn (EntryRepository $r) => $r->fetchBySlug('launch')],
        ];
    }

    #[DataProvider('unboundCalls')]
    public function testAnUnboundRepositoryThrows(callable $call): void
    {
        $this->expectException(LogicException::class);

        $call($this->entries);
    }

    public function testSlugsAreUniquePerType(): void
    {
        $news = $this->entries->forType('press_release');
        $first = $news->create(['title' => 'Launch']);
        $second = $news->create(['title' => 'Launch']);
        $other = $this->entries->forType('blog_post')->create(['title' => 'Launch']);

        $this->assertSame('launch', $first->getSlug());
        $this->assertSame('launch-1', $second->getSlug());
        $this->assertSame('launch', $other->getSlug());
    }

    public function testFetchBySlugAndFetchOnlySeeTheBoundType(): void
    {
        $news = $this->entries->forType('press_release')->create(['title' => 'Launch']);
        $post = $this->entries->forType('blog_post')->create(['title' => 'Launch']);

        $this->assertSame($news, $this->entries->forType('press_release')->fetchBySlug('launch'));
        $this->assertSame($post, $this->entries->forType('blog_post')->fetchBySlug('launch'));
        $this->assertNull($this->entries->forType('press_release')->fetch($post->getId()));
    }

    public function testListQueriesOnlySeeTheBoundType(): void
    {
        $news = $this->entries->forType('press_release');
        $news->create(['title' => 'One', 'status' => 'published']);
        $news->create(['title' => 'Two', 'status' => 'published']);
        $post = $this->entries->forType('blog_post')->create(['title' => 'Three', 'status' => 'published']);

        $this->assertCount(2, $news->fetchAll());
        $this->assertCount(2, $news->query());
        $this->assertSame(2, $news->fetchPage()['total']);
        $this->assertSame([], $news->fetchPublicByIds([$post->getId()]));
    }

    public function testFetchPublicByIdsLeavesOutDrafts(): void
    {
        $news = $this->entries->forType('press_release');
        $published = $news->create(['title' => 'Out', 'status' => 'published']);
        $draft = $news->create(['title' => 'Not yet']);

        $this->assertSame([$published], $news->fetchPublicByIds([$published->getId(), $draft->getId()]));
    }

    public function testUpdateSlugKeepsSlugsUniqueWithinTheType(): void
    {
        $news = $this->entries->forType('press_release');
        $news->create(['title' => 'Taken']);
        $entry = $news->create(['title' => 'Original']);

        $news->updateSlug($entry, 'taken');

        $this->assertSame('taken-1', $entry->getSlug());
    }

    public function testUpdateSlugLeavesABlankOrUnchangedSlugAlone(): void
    {
        $news = $this->entries->forType('press_release');
        $entry = $news->create(['title' => 'Original']);

        $news->updateSlug($entry, '  ');
        $news->updateSlug($entry, 'ORIGINAL');

        $this->assertSame('original', $entry->getSlug());
    }

    public function testUpdateSlugRejectsAnEntryOfAnotherType(): void
    {
        $post = $this->entries->forType('blog_post')->create(['title' => 'Launch']);

        $this->expectException(InvalidArgumentException::class);
        $this->entries->forType('press_release')->updateSlug($post, 'new-slug');
    }

    public function testSearchMatchesTitleOrExcerpt(): void
    {
        $news = $this->entries->forType('press_release');
        $news->create(['title' => 'Gala Night', 'excerpt' => 'Black tie']);
        $news->create(['title' => 'Auditions', 'excerpt' => 'For the gala chorus']);
        $news->create(['title' => 'Box office hours']);
        $this->entries->forType('blog_post')->create(['title' => 'Gala recap']);

        $titles = array_map(static fn (Entry $e) => $e->getTitle(), $news->fetchPage(search: 'gala')['items']);
        sort($titles);

        $this->assertSame(['Auditions', 'Gala Night'], $titles);
    }

    public function testSortByTitle(): void
    {
        $news = $this->entries->forType('press_release');
        foreach (['Bravo', 'Alpha', 'Charlie'] as $title) {
            $news->create(['title' => $title]);
        }

        $items = $news->fetchPage(sort: 'title', direction: 'desc')['items'];

        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], array_map(static fn (Entry $e) => $e->getTitle(), $items));
    }

    public function testUpdateAndDeleteRejectAnEntryOfAnotherType(): void
    {
        $post = $this->entries->forType('blog_post')->create(['title' => 'Launch']);
        $news = $this->entries->forType('press_release');

        try {
            $news->delete($post);
            $this->fail('Expected delete() to reject an entry of another type.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $news->update($post);
    }

    public function testDeleteRemovesTheEntryAndItsTermAssignments(): void
    {
        $news = $this->entries->forType('press_release');
        $entry = $news->create(['title' => 'Launch']);
        $this->em->getConnection()->insert('terms', [
            'id' => 1,
            'taxonomy' => 'topic',
            'name' => 'News',
            'slug' => 'news',
            'created_at' => '2026-10-16 00:00:00',
            'modified_at' => '2026-10-16 00:00:00',
        ]);
        $this->em->getConnection()->insert('term_relationships', [
            'term_id' => 1,
            'content_type' => 'press_release',
            'content_id' => $entry->getId(),
            'position' => 0,
        ]);
        $id = $entry->getId();
        $this->assertCount(1, $this->em->getRepository(TermRelationship::class)->findBy(['contentId' => $id]));

        $news->delete($entry);

        $this->assertNull($news->fetch($id));
        $this->assertSame([], $this->em->getRepository(TermRelationship::class)->findBy([
            'contentType' => 'press_release',
            'contentId' => $id,
        ]));
    }
}
