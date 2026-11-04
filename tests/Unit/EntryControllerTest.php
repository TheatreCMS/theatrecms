<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\ContentType\ContentTypeRegistry;
use TheatreCMS\Controllers\EntryController;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Entry;
use TheatreCMS\Models\Media;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\EntryRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;
use TheatreCMS\Theme\TemplateResolver;

/**
 * The admin screens of EntryController, run against three fixture types: `press_release`
 * (editor, excerpt and featured image), `notice` (no optional fields) and `program_page`
 * (hierarchical).
 */
#[AllowMockObjectsWithoutExpectations]
class EntryControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private EntryRepository $entries;
    private ContentMetaRepository $meta;
    private TermRepository $terms;
    private TermRelationshipRepository $relationships;

    private ContentTypeDefinition $pressRelease;
    private ContentTypeDefinition $notice;
    private ContentTypeDefinition $programPage;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();

        $contentTypes = new ContentTypeRegistry();
        $contentTypes->register('press_release');
        $taxonomies = new TaxonomyRegistry($contentTypes);
        $taxonomies->register('press_category', ['press_release']);

        $this->entries = new EntryRepository($this->em);
        $this->meta = new ContentMetaRepository($this->em);
        $this->terms = new TermRepository($this->em, $taxonomies);
        $this->relationships = new TermRelationshipRepository($this->em, $taxonomies);

        $this->pressRelease = new ContentTypeDefinition(
            name: 'press_release',
            label: 'Press Releases',
            singularLabel: 'Press Release',
            urlPrefix: 'press-releases',
            supports: ['editor', 'excerpt', 'featured_image'],
        );
        $this->notice = new ContentTypeDefinition(
            name: 'notice',
            label: 'Notices',
            singularLabel: 'Notice',
            urlPrefix: 'notices',
            supports: [],
        );
        $this->programPage = new ContentTypeDefinition(
            name: 'program_page',
            label: 'Program Pages',
            singularLabel: 'Program Page',
            urlPrefix: 'program-pages',
            hierarchical: true,
        );
    }

    public function testIndexListsOnlyItsOwnTypeAndSwitchesTemplate(): void
    {
        $this->entry('Season announced');
        $this->entry('Auditions');
        $this->entry('Box office closed', 'notice');

        $this->controller()->index($this->request('GET', query: ['sort' => 'title']), new Response());
        $this->assertSame('admin/entries/index.html.twig', $this->rendered['template']);
        $this->assertSame(['Auditions', 'Season announced'], $this->titles($this->rendered['data']['entries']));
        $this->assertSame($this->pressRelease, $this->rendered['data']['content_type']);

        $this->controller()->index($this->request('GET', htmx: true, query: ['q' => 'Season']), new Response());
        $this->assertSame('admin/entries/_list.html.twig', $this->rendered['template']);
        $this->assertSame(['Season announced'], $this->titles($this->rendered['data']['entries']));
    }

    public function testCreateOffersParentsOnlyForHierarchicalTypes(): void
    {
        $this->entry('Press kit');
        $parent = $this->entry('Programs', 'program_page');

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/entries/create.html.twig', $this->rendered['template']);
        $this->assertSame($this->pressRelease, $this->rendered['data']['content_type']);
        $this->assertSame([], $this->rendered['data']['parents']);

        $this->controller($this->programPage)->create($this->request('GET'), new Response());
        $this->assertContains($parent, $this->rendered['data']['parents']);
    }

    public function testEditRendersItsOwnTypeAndReturns404Otherwise(): void
    {
        $entry = $this->entry('Season announced');
        $notice = $this->entry('Box office closed', 'notice');

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $entry->getId()]);
        $this->assertSame('admin/entries/edit.html.twig', $this->rendered['template']);
        $this->assertSame($entry, $this->rendered['data']['entry']);

        $missing = $this->controller()->edit($this->request('GET'), new Response(), ['id' => 999]);
        $this->assertSame(404, $missing->getStatusCode());

        $otherType = $this->controller()->edit($this->request('GET'), new Response(), ['id' => $notice->getId()]);
        $this->assertSame(404, $otherType->getStatusCode());
    }

    /**
     * @return array<string, array{0: callable(self): array<string, mixed>}>
     */
    public static function invalidStoreBodies(): array
    {
        return [
            'empty body' => [fn(self $test): array => []],
            'missing title' => [fn(self $test): array => ['content' => '{"blocks":[]}']],
            'non-image featured media' => [fn(self $test): array => [
                'title' => 'Season announced',
                'featuredImageId' => (string) $test->createTestMedia($test->em, Media::TYPE_PDF)->getId(),
            ]],
        ];
    }

    /**
     * @param callable(self): array<string, mixed> $body
     */
    #[DataProvider('invalidStoreBodies')]
    public function testStoreRejectsInvalidInput(callable $body): void
    {
        $data = $body($this);

        $result = $this->controller()->store($this->request('POST', $data), new Response());
        $this->assertSame(400, $result->getStatusCode());

        $this->rendered = null;
        $this->controller()->store($this->request('POST', $data, htmx: true), new Response());
        $this->assertSame('admin/partials/_alert.html.twig', $this->rendered['template']);
        $this->assertSame('error', $this->rendered['data']['type']);
    }

    public function testStoreCreatesAnEntryWithSupportedFieldsAndTerms(): void
    {
        $image = $this->createTestMedia($this->em);
        $category = $this->terms->create(['taxonomy' => 'press_category', 'name' => 'Announcements']);

        $result = $this->controller()->store($this->request('POST', [
            'title' => 'Season announced',
            'status' => 'published',
            'content' => '{"blocks":[]}',
            'excerpt' => 'Our new season',
            'featuredImageId' => (string) $image->getId(),
            'terms' => ['press_category' => [(string) $category->getId()]],
        ], htmx: true), new Response());

        $entry = $this->entries->forType('press_release')->fetchBySlug('season-announced');
        $this->assertNotNull($entry);
        $this->assertSame('/admin/press-releases/edit/' . $entry->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame('press_release', $entry->getContentType());
        $this->assertSame(ContentStatus::PUBLISHED, $entry->getStatus());
        $this->assertSame('{"blocks":[]}', $entry->getContent());
        $this->assertSame('Our new season', $entry->getExcerpt());
        $this->assertSame($image, $entry->getFeaturedImage());
        $this->assertCount(1, $this->relationships->termsFor('press_release', $entry->getId()));

        $result = $this->controller()->store($this->request('POST', ['title' => 'Second']), new Response());
        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringStartsWith('/admin/press-releases/edit/', $result->getHeaderLine('Location'));
    }

    public function testStoreDefaultsToDraft(): void
    {
        $this->controller()->store($this->request('POST', ['title' => 'Season announced']), new Response());

        $entry = $this->entries->forType('press_release')->fetchBySlug('season-announced');
        $this->assertSame(ContentStatus::DRAFT, $entry->getStatus());
    }

    public function testStoreIgnoresFieldsTheTypeDoesNotSupport(): void
    {
        $image = $this->createTestMedia($this->em);

        $this->controller($this->notice)->store($this->request('POST', [
            'title' => 'Box office closed',
            'content' => '{"blocks":[]}',
            'excerpt' => 'Closed today',
            'featuredImageId' => (string) $image->getId(),
        ]), new Response());

        $entry = $this->entries->forType('notice')->fetchBySlug('box-office-closed');
        $this->assertNull($entry->getContent());
        $this->assertNull($entry->getExcerpt());
        $this->assertNull($entry->getFeaturedImage());
    }

    /**
     * @return array<string, array{0: callable(Entry, Entry): array<string, mixed>, 1: int}>
     */
    public static function invalidUpdateBodies(): array
    {
        return [
            'empty body' => [fn(Entry $own, Entry $other): array => [], 400],
            'unknown id' => [fn(Entry $own, Entry $other): array => ['entryId' => '999', 'title' => 'x'], 404],
            'id of another type' => [
                fn(Entry $own, Entry $other): array => ['entryId' => (string) $other->getId(), 'title' => 'x'],
                404,
            ],
            'missing title' => [fn(Entry $own, Entry $other): array => ['entryId' => (string) $own->getId()], 400],
            'unknown status' => [
                fn(Entry $own, Entry $other): array => [
                    'entryId' => (string) $own->getId(),
                    'title' => 'x',
                    'status' => 'bogus',
                ],
                400,
            ],
        ];
    }

    /**
     * @param callable(Entry, Entry): array<string, mixed> $body
     */
    #[DataProvider('invalidUpdateBodies')]
    public function testUpdateRejectsInvalidInput(callable $body, int $status): void
    {
        $data = $body($this->entry('Season announced'), $this->entry('Box office closed', 'notice'));

        $result = $this->controller()->update($this->request('POST', $data), new Response());
        $this->assertSame($status, $result->getStatusCode());

        $this->rendered = null;
        $this->controller()->update($this->request('POST', $data, htmx: true), new Response());
        $this->assertSame('admin/partials/_alert.html.twig', $this->rendered['template']);
        $this->assertSame('error', $this->rendered['data']['type']);
    }

    public function testUpdateAppliesFieldsAndRendersSavedPartial(): void
    {
        $entry = $this->entry('Season announced');

        $this->controller()->update($this->request('POST', [
            'entryId' => (string) $entry->getId(),
            'title' => 'Big season announced',
            'status' => 'published',
            'publishedAt' => '2026-01-02T03:04',
            'slug' => 'big-season',
            'content' => '{"blocks":[]}',
            'excerpt' => 'Our biggest season',
        ], htmx: true), new Response());

        $this->assertSame('admin/entries/_saved.html.twig', $this->rendered['template']);
        $this->assertSame($entry, $this->rendered['data']['entry']);
        $this->assertSame('Big season announced', $entry->getTitle());
        $this->assertSame(ContentStatus::PUBLISHED, $entry->getStatus());
        $this->assertSame('2026-01-02 03:04', $entry->getPublishedAt()->format('Y-m-d H:i'));
        $this->assertSame('big-season', $entry->getSlug());
        $this->assertSame('{"blocks":[]}', $entry->getContent());
        $this->assertSame('Our biggest season', $entry->getExcerpt());
    }

    public function testUpdateStampsPublishedAtOnceAndClearsItForDrafts(): void
    {
        $entry = $this->entry('Season announced');
        $base = ['entryId' => (string) $entry->getId(), 'title' => 'Season announced'];

        $this->controller()->update($this->request('POST', $base + ['status' => 'published']), new Response());
        $publishedAt = $entry->getPublishedAt();
        $this->assertNotNull($publishedAt);

        $this->controller()->update($this->request('POST', $base + ['status' => 'published']), new Response());
        $this->assertSame($publishedAt, $entry->getPublishedAt());

        $result = $this->controller()->update($this->request('POST', $base + ['status' => 'draft']), new Response());
        $this->assertNull($entry->getPublishedAt());
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/admin/press-releases', $result->getHeaderLine('Location'));
    }

    public function testStoreAndUpdateSetTheParentOfHierarchicalTypes(): void
    {
        $programs = $this->entry('Programs', 'program_page');
        $archive = $this->entry('Archive', 'program_page');

        $this->controller($this->programPage)->store($this->request('POST', [
            'title' => 'Spring',
            'parentId' => (string) $programs->getId(),
        ]), new Response());

        $spring = $this->entries->forType('program_page')->fetchBySlug('spring');
        $this->assertSame($programs, $spring->getParent());

        $this->controller($this->programPage)->update($this->request('POST', [
            'entryId' => (string) $spring->getId(),
            'title' => 'Spring',
            'parentId' => (string) $archive->getId(),
        ]), new Response());
        $this->assertSame($archive, $spring->getParent());
    }

    public function testFlatTypesIgnoreParentId(): void
    {
        $other = $this->entry('Press kit');

        $this->controller()->store($this->request('POST', [
            'title' => 'Season announced',
            'parentId' => (string) $other->getId(),
        ]), new Response());

        $entry = $this->entries->forType('press_release')->fetchBySlug('season-announced');
        $this->assertNull($entry->getParent());
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->entry('A');
        $b = $this->entry('B');

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/entries/_list.html.twig', $this->rendered['template']);
        $this->assertSame(['B'], $this->titles($this->rendered['data']['entries']));

        $bId = $b->getId();
        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $bId]);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/admin/press-releases', $result->getHeaderLine('Location'));
        $this->assertNull($this->entries->forType('press_release')->fetch($bId));
    }

    public function testDestroyLeavesEntriesOfAnotherTypeAlone(): void
    {
        $notice = $this->entry('Box office closed', 'notice');

        $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $notice->getId()]);

        $this->assertNotNull($this->entries->forType('notice')->fetch($notice->getId()));
    }

    /**
     * @param Entry[] $entries
     * @return string[]
     */
    private function titles(array $entries): array
    {
        return array_map(fn(Entry $entry) => $entry->getTitle(), $entries);
    }

    private function entry(string $title, string $type = 'press_release'): Entry
    {
        return $this->entries->forType($type)->create(['title' => $title]);
    }

    private function controller(?ContentTypeDefinition $type = null): EntryController
    {
        return new EntryController(
            $this->entries,
            $this->em,
            $this->recordingTwig(),
            $type ?? $this->pressRelease,
            $this->meta,
            $this->relationships,
            $this->createStub(TemplateResolver::class)
        );
    }
}
