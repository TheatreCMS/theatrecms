<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\ContentType\ContentTypeDefinition;
use TheatreCMS\Controllers\PageController;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Page;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;
use TheatreCMS\Theme\TemplateResolver;

#[AllowMockObjectsWithoutExpectations]
class PageControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private PageRepository $pages;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->pages = new PageRepository($this->em, new ContentMetaRepository($this->em));
    }

    public function testIndexCreateAndEditRender(): void
    {
        $page = $this->page('About');

        $this->controller()->index($this->request('GET'), new Response());
        $this->assertSame('admin/pages/index.html.twig', $this->rendered['template']);
        $this->assertSame(ContentStatus::labels(), $this->rendered['data']['statuses']);
        $this->assertCount(1, $this->rendered['data']['pages']);

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/pages/create.html.twig', $this->rendered['template']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $page->getId()]);
        $this->assertSame($page, $this->rendered['data']['page']);

        $this->assertSame(404, $this->controller()->edit($this->request('GET'), new Response(), ['id' => 999])->getStatusCode());
    }

    public function testStoreRejectsEmptyBody(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());

        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('No data received.', $this->rendered['data']['message']);
    }

    public function testStoreReportsValidationErrors(): void
    {
        $result = $this->controller()->store($this->request('POST', ['title' => 'About']), new Response());
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('Content is required.', (string) $result->getBody());

        $this->controller()->store($this->request('POST', ['content' => 'x'], htmx: true), new Response());
        $this->assertSame('Title is required.', $this->rendered['data']['message']);
    }

    public function testStoreCreatesPageAndRedirects(): void
    {
        $result = $this->controller()->store(
            $this->request('POST', ['title' => 'About', 'content' => '{"blocks":[]}'], htmx: true),
            new Response()
        );
        $page = $this->pages->fetchBySlug('about');
        $this->assertSame(ContentStatus::DRAFT, $page->getStatus());
        $this->assertSame('/admin/pages/edit/' . $page->getId(), $result->getHeaderLine('HX-Redirect'));

        $result = $this->controller()->store(
            $this->request('POST', ['title' => 'Contact', 'content' => 'x', 'status' => 'published']),
            new Response()
        );
        $this->assertStringStartsWith('/admin/pages/edit/', $result->getHeaderLine('Location'));
    }

    public function testUpdateValidation(): void
    {
        $page = $this->page('About');
        $id = (string) $page->getId();

        $cases = [
            [[], 400, 'No data received.'],
            [['pageId' => '999', 'title' => 'x'], 404, 'Page not found.'],
            [['pageId' => $id, 'title' => 'x'], 400, 'Title and content are required.'],
            [['pageId' => $id, 'title' => 'x', 'content' => 'y', 'status' => 'bogus'], 400, 'Invalid status.'],
        ];

        foreach ($cases as [$body, $status, $message]) {
            $this->assertSame($status, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());
            $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
            $this->assertSame($message, $this->rendered['data']['message']);
        }
    }

    public function testUpdateSavesPageAndSlug(): void
    {
        $page = $this->page('About');
        $this->page('Taken');

        $this->controller()->update($this->request('POST', [
            'pageId' => (string) $page->getId(),
            'title' => 'About us',
            'content' => 'new',
            'status' => 'published',
            'slug' => 'taken',
        ], htmx: true), new Response());

        $this->assertSame('Page saved successfully.', $this->rendered['data']['message']);
        $this->assertSame('About us', $page->getTitle());
        $this->assertSame(ContentStatus::PUBLISHED, $page->getStatus());
        $this->assertSame('taken-1', $page->getSlug());

        $result = $this->controller()->update($this->request('POST', [
            'pageId' => (string) $page->getId(),
            'title' => 'About us',
            'content' => 'new',
            'slug' => 'TAKEN-1',
        ]), new Response());
        $this->assertSame('/admin/pages', $result->getHeaderLine('Location'));
        $this->assertSame('taken-1', $page->getSlug());
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->page('A');
        $b = $this->page('B');

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/pages/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['pages']);

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/pages', $result->getHeaderLine('Location'));
    }

    public function testUpdateMovesThePageUnderANewParent(): void
    {
        $about = $this->page('About');
        $board = $this->page('Board');

        $this->controller()->update($this->request('POST', [
            'pageId' => (string) $board->getId(),
            'title' => 'Board',
            'content' => 'body',
            'parentId' => (string) $about->getId(),
        ], htmx: true), new Response());

        $this->assertSame($about, $board->getParent());
    }

    public function testUpdateRejectsADescendantAsParent(): void
    {
        $about = $this->page('About');
        $board = $this->pages->create(['title' => 'Board', 'content' => 'body', 'parentId' => $about->getId()]);

        $result = $this->controller()->update($this->request('POST', [
            'pageId' => (string) $about->getId(),
            'title' => 'About',
            'content' => 'body',
            'parentId' => (string) $board->getId(),
        ]), new Response());

        $this->assertSame(400, $result->getStatusCode());
        $this->assertNull($about->getParent());
    }

    public function testSingleRendersThePublishedPageAtItsPath(): void
    {
        $about = $this->pages->create(['title' => 'About', 'content' => 'body', 'status' => 'published']);
        $board = $this->pages->create([
            'title' => 'Board of Directors',
            'content' => 'body',
            'status' => 'published',
            'parentId' => $about->getId(),
        ]);
        $definition = new ContentTypeDefinition('page', 'Pages', 'Page', '', hasArchive: false, hierarchical: true);

        $resolver = $this->createMock(TemplateResolver::class);
        $resolver->expects($this->once())
            ->method('renderSingle')
            ->with($this->anything(), $this->anything(), 'page', $board, ['page' => $board], 'pages')
            ->willReturn(new Response());

        $request = $this->request('GET')->withAttribute(ContentTypeDefinition::class, $definition);
        $this->controller($resolver)->single($request, new Response(), ['path' => 'about/board-of-directors']);

        $missing = $this->controller()->single($request, new Response(), ['path' => 'board-of-directors']);
        $this->assertSame(404, $missing->getStatusCode());
    }

    public function testHeroImageAndTextAreSavedLoadedAndCleared(): void
    {
        $meta = new ContentMetaRepository($this->em);
        $image = $this->createTestMedia($this->em);

        $this->controller()->store($this->request('POST', [
            'title' => 'About',
            'content' => 'body',
            'heroImageId' => (string) $image->getId(),
            'heroText' => 'Family fun for everyone',
        ], htmx: true), new Response());
        $page = $this->pages->fetchBySlug('about');
        $this->assertSame((string) $image->getId(), $meta->get('page', $page->getId(), 'hero_image_id'));
        $this->assertSame('Family fun for everyone', $meta->get('page', $page->getId(), 'hero_text'));

        $this->em->clear();
        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $page->getId()]);
        $this->assertSame((string) $image->getId(), $this->rendered['data']['heroImageId']);
        $this->assertTrue($this->rendered['data']['page']->hasHeroImage());
        $this->assertSame($image->getUrl(), $this->rendered['data']['page']->getHeroImageUrl());
        $this->assertSame('Family fun for everyone', $this->rendered['data']['page']->getHeroText());

        $this->controller()->update($this->request('POST', [
            'pageId' => (string) $page->getId(),
            'title' => 'About',
            'content' => 'body',
            'heroImageId' => '',
            'heroText' => '',
        ], htmx: true), new Response());
        $this->assertNull($meta->get('page', $page->getId(), 'hero_image_id'));
        $this->assertNull($meta->get('page', $page->getId(), 'hero_text'));
    }

    public function testHeroImageIsIgnoredWhenPagesDoNotSupportIt(): void
    {
        $image = $this->createTestMedia($this->em);
        $definition = new ContentTypeDefinition('page', 'Pages', 'Page', '', supports: ['editor']);
        $request = $this->request('POST', [
            'title' => 'About',
            'content' => 'body',
            'heroImageId' => (string) $image->getId(),
            'heroText' => 'Ignored',
        ])->withAttribute(ContentTypeDefinition::class, $definition);

        $this->controller()->store($request, new Response());
        $page = $this->pages->fetchBySlug('about');
        $meta = new ContentMetaRepository($this->em);
        $this->assertNull($meta->get('page', $page->getId(), 'hero_image_id'));
        $this->assertNull($meta->get('page', $page->getId(), 'hero_text'));
    }

    public function testPublicFetchByPathCarriesTheHeroImage(): void
    {
        $image = $this->createTestMedia($this->em);
        $about = $this->pages->create(['title' => 'About', 'content' => 'body', 'status' => 'published']);
        (new ContentMetaRepository($this->em))->set('page', $about->getId(), 'hero_image_id', (string) $image->getId());
        $this->em->clear();

        $this->assertSame($image->getUrl(), $this->pages->fetchPublicByPath('about')->getHeroImageUrl());
    }

    private function page(string $title): Page
    {
        return $this->pages->create(['title' => $title, 'content' => 'body']);
    }

    private function controller(?TemplateResolver $resolver = null): PageController
    {
        return new PageController(
            $this->pages,
            $this->em,
            $this->recordingTwig(),
            $resolver ?? $this->createStub(TemplateResolver::class),
            new ContentMetaRepository($this->em)
        );
    }
}
