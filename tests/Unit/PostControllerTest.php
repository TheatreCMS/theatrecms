<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\Controllers\PostController;
use TheatreCMS\Enums\ContentStatus;
use TheatreCMS\Models\Post;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\TermRelationshipRepository;
use TheatreCMS\Repositories\TermRepository;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;

#[AllowMockObjectsWithoutExpectations]
class PostControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private PostRepository $posts;
    private ContentMetaRepository $meta;
    private TermRepository $terms;
    private TermRelationshipRepository $relationships;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $registry = new TaxonomyRegistry();
        $registry->register('post_category', ['post'], ['multiple' => false]);
        $this->meta = new ContentMetaRepository($this->em);
        $this->posts = new PostRepository($this->em, $this->meta);
        $this->terms = new TermRepository($this->em, $registry);
        $this->relationships = new TermRelationshipRepository($this->em, $registry);
    }

    public function testIndexSearchesSortsAndSwitchesTemplate(): void
    {
        $this->post('Opening night');
        $this->post('Auditions');

        $this->controller()->index($this->request('GET', query: ['sort' => 'title']), new Response());
        $this->assertSame('admin/posts/index.html.twig', $this->rendered['template']);
        $this->assertSame(['Auditions', 'Opening night'], $this->titles($this->rendered['data']['posts']));

        $this->controller()->index($this->request('GET', htmx: true, query: ['q' => 'Open', 'sort' => 'publishedAt']), new Response());
        $this->assertSame('admin/posts/_list.html.twig', $this->rendered['template']);
        $this->assertSame(['Opening night'], $this->titles($this->rendered['data']['posts']));
    }

    public function testCreateAndEditRender(): void
    {
        $post = $this->post('News');

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/posts/create.html.twig', $this->rendered['template']);

        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $post->getId()]);
        $this->assertSame($post, $this->rendered['data']['post']);
        $this->assertNull($this->rendered['data']['heroImageId']);

        $this->assertSame(404, $this->controller()->edit($this->request('GET'), new Response(), ['id' => 99])->getStatusCode());
    }

    public function testStoreRejectsEmptyAndInvalidInput(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());
        $this->controller()->store($this->request('POST', htmx: true), new Response());
        $this->assertSame('Unable to create post. Please check your input.', $this->rendered['data']['message']);

        $result = $this->controller()->store($this->request('POST', ['title' => 'News']), new Response());
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('Content is required.', (string) $result->getBody());

        $this->rendered = null;
        $this->controller()->store($this->request('POST', ['title' => 'News'], htmx: true), new Response());
        $this->assertSame('error', $this->rendered['data']['type']);
    }

    public function testStoreCreatesPostWithImagesAndTerms(): void
    {
        $image = $this->createTestMedia($this->em);
        $news = $this->terms->create(['taxonomy' => 'post_category', 'name' => 'News']);

        $result = $this->controller()->store($this->request('POST', [
            'title' => 'Opening night',
            'content' => '{"blocks":[]}',
            'featuredImageId' => (string) $image->getId(),
            'heroImageId' => (string) $image->getId(),
            'terms' => ['post_category' => [(string) $news->getId()]],
        ], htmx: true), new Response());

        $post = $this->posts->fetchBySlug('opening-night');
        $this->assertSame('/admin/posts/edit/' . $post->getId(), $result->getHeaderLine('HX-Redirect'));
        $this->assertSame($image, $post->getFeaturedImage());
        $this->assertCount(1, $this->relationships->termsFor('post', $post->getId()));

        $result = $this->controller()->store($this->request('POST', ['title' => 'Second', 'content' => 'x']), new Response());
        $this->assertStringStartsWith('/admin/posts/edit/', $result->getHeaderLine('Location'));
    }

    public function testUpdateValidation(): void
    {
        $id = (string) $this->post('News')->getId();

        $cases = [
            [[], 400],
            [['postId' => '99', 'title' => 'x'], 404],
            [['postId' => $id, 'title' => 'x'], 400],
            [['postId' => $id, 'title' => 'x', 'content' => 'y', 'status' => 'bogus'], 400],
        ];

        foreach ($cases as [$body, $status]) {
            $this->assertSame($status, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());
            $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
            $this->assertSame('Unable to save post. Please check your input.', $this->rendered['data']['message']);
        }
    }

    public function testUpdateSetsExplicitPublishedAtAndSlug(): void
    {
        $post = $this->post('News');

        $this->controller()->update($this->request('POST', [
            'postId' => (string) $post->getId(),
            'title' => 'Big news',
            'content' => 'body',
            'status' => 'published',
            'publishedAt' => '2026-01-02T03:04',
            'slug' => 'big-news',
        ], htmx: true), new Response());

        $this->assertSame('admin/posts/_saved.html.twig', $this->rendered['template']);
        $this->assertSame('Big news', $post->getTitle());
        $this->assertSame(ContentStatus::PUBLISHED, $post->getStatus());
        $this->assertSame('2026-01-02 03:04', $post->getPublishedAt()->format('Y-m-d H:i'));
        $this->assertSame('big-news', $post->getSlug());
    }

    public function testUpdateDefaultsPublishedAtWhenPublishingAndClearsItForDrafts(): void
    {
        $post = $this->post('News');
        $base = ['postId' => (string) $post->getId(), 'title' => 'News', 'content' => 'body'];

        $this->controller()->update($this->request('POST', $base + ['status' => 'published']), new Response());
        $this->assertNotNull($post->getPublishedAt());

        $result = $this->controller()->update($this->request('POST', $base + ['status' => 'draft']), new Response());
        $this->assertNull($post->getPublishedAt());
        $this->assertSame('/admin/posts', $result->getHeaderLine('Location'));
    }

    public function testUpdateReportsInvalidTerms(): void
    {
        $post = $this->post('News');
        $a = $this->terms->create(['taxonomy' => 'post_category', 'name' => 'A']);
        $b = $this->terms->create(['taxonomy' => 'post_category', 'name' => 'B']);
        $body = [
            'postId' => (string) $post->getId(),
            'title' => 'News',
            'content' => 'body',
            'terms' => ['post_category' => [(string) $a->getId(), (string) $b->getId()]],
        ];

        $this->assertSame(400, $this->controller()->update($this->request('POST', $body), new Response())->getStatusCode());

        $this->controller()->update($this->request('POST', $body, htmx: true), new Response());
        $this->assertStringStartsWith('Unable to save post: ', $this->rendered['data']['message']);
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->post('A');
        $b = $this->post('B');

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/posts/_list.html.twig', $this->rendered['template']);
        $this->assertSame(['B'], $this->titles($this->rendered['data']['posts']));

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/posts', $result->getHeaderLine('Location'));
    }

    /**
     * @param Post[] $posts
     * @return string[]
     */
    private function titles(array $posts): array
    {
        return array_map(fn(Post $p) => $p->getTitle(), $posts);
    }

    private function post(string $title): Post
    {
        return $this->posts->create(['title' => $title, 'content' => 'body']);
    }

    private function controller(): PostController
    {
        return new PostController($this->posts, $this->em, $this->recordingTwig(), $this->meta, $this->relationships);
    }
}
