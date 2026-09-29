<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use TheatreCMS\Controllers\MenuController;
use TheatreCMS\Menus\MenuItemResolver;
use TheatreCMS\Models\Menu;
use TheatreCMS\Repositories\ContentMetaRepository;
use TheatreCMS\Repositories\MenuRepository;
use TheatreCMS\Repositories\PageRepository;
use TheatreCMS\Repositories\PostRepository;
use TheatreCMS\Repositories\ProductionRepository;
use TheatreCMS\Repositories\SeasonRepository;
use TheatreCMS\Services\MenuLinkTargetOptionsService;
use TheatreCMS\Taxonomy\TaxonomyRegistry;
use TheatreCMS\Tests\Includes\RendersControllerViews;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;
use TheatreCMS\Theme\ContentTypeRegistry;
use TheatreCMS\Theme\MenuLocationRegistry;
use TheatreCMS\Theme\PermalinkResolver;

#[AllowMockObjectsWithoutExpectations]
class MenuControllerTest extends TestCase
{
    use UsesSqliteEntityManager;
    use RendersControllerViews;

    private EntityManager $em;
    private MenuRepository $menus;
    private PageRepository $pages;
    private PostRepository $posts;
    private ProductionRepository $productions;
    private SeasonRepository $seasons;
    private MenuLocationRegistry $locations;
    private MenuItemResolver $resolver;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $meta = new ContentMetaRepository($this->em);
        $this->menus = new MenuRepository($this->em);
        $this->pages = new PageRepository($this->em);
        $this->posts = new PostRepository($this->em, $meta);
        $this->productions = new ProductionRepository($this->em, $meta);
        $this->seasons = new SeasonRepository($this->em, $meta);
        $this->locations = new MenuLocationRegistry();
        $this->locations->register('primary', 'Primary');
        $contentTypes = new ContentTypeRegistry();
        $this->resolver = new MenuItemResolver(
            $this->pages,
            $this->posts,
            $this->productions,
            $this->seasons,
            new PermalinkResolver($contentTypes, new TaxonomyRegistry()),
            $contentTypes,
        );
    }

    public function testIndexAndCreateRenderMenusAndLocations(): void
    {
        $this->menus->create(['name' => 'Zeta']);
        $this->menus->create(['name' => 'Alpha', 'location' => 'primary']);

        $this->controller()->index($this->request('GET'), new Response());
        $this->assertSame('admin/menus/index.html.twig', $this->rendered['template']);
        $this->assertSame(['Alpha', 'Zeta'], array_map(fn(Menu $m) => $m->getName(), $this->rendered['data']['menus']));
        $this->assertSame(['primary' => 'Primary'], $this->rendered['data']['locations']);

        $this->controller()->create($this->request('GET'), new Response());
        $this->assertSame('admin/menus/create.html.twig', $this->rendered['template']);
    }

    public function testStoreValidatesAndCreates(): void
    {
        $this->assertSame(400, $this->controller()->store($this->request('POST'), new Response())->getStatusCode());

        $result = $this->controller()->store($this->request('POST', ['location' => 'primary']), new Response());
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('Name is required.', (string) $result->getBody());

        $result = $this->controller()->store($this->request('POST', ['name' => 'Main', 'location' => 'primary']), new Response());
        $menu = $this->menus->findByLocation('primary');
        $this->assertSame('/admin/menus/edit/' . $menu->getId(), $result->getHeaderLine('Location'));

        $result = $this->controller()->store($this->request('POST', ['name' => 'Other', 'location' => 'primary']), new Response());
        $this->assertSame('That location is already assigned to another menu.', (string) $result->getBody());
    }

    public function testSaveTreePersistsNestedItemsAndEditRendersThem(): void
    {
        $menu = $this->menus->create(['name' => 'Main']);
        $page = $this->pages->create(['title' => 'About', 'content' => 'x']);
        $post = $this->posts->create(['title' => 'News item', 'content' => 'x']);
        $season = $this->seasons->create(['label' => '2025-26', 'startDate' => '2025-09-01', 'endDate' => '2026-06-01']);
        $production = $this->productions->create(['name' => 'Hamlet', 'seasonId' => $season->getId()]);

        $items = [
            ['clientId' => 'a', 'linkType' => 'page', 'targetId' => (string) $page->getId(), 'position' => 0],
            ['clientId' => 'b', 'parentClientId' => 'a', 'linkType' => 'custom', 'customUrl' => 'https://example.com', 'label' => 'Ext'],
            ['clientId' => 'c', 'linkType' => 'post', 'targetId' => (string) $post->getId(), 'position' => 1],
            ['clientId' => 'd', 'linkType' => 'production', 'targetId' => (string) $production->getId(), 'position' => 2],
            ['clientId' => 'e', 'linkType' => 'season', 'targetId' => (string) $season->getId(), 'position' => 3],
            ['clientId' => 'f', 'linkType' => 'post', 'targetId' => '', 'position' => 4],
            ['clientId' => 'g', 'linkType' => 'season', 'targetId' => '999', 'position' => 5],
        ];

        $this->controller()->saveTree($this->request('POST', [
            'name' => 'Main nav',
            'location' => 'primary',
            'items' => json_encode($items),
        ], htmx: true), new Response(), ['id' => $menu->getId()]);

        $this->assertSame('Menu saved successfully.', $this->rendered['data']['message']);
        $this->assertSame('Main nav', $menu->getName());
        $this->assertSame('primary', $menu->getLocation());
        $this->assertCount(7, $menu->getItems());
        $this->assertCount(6, $menu->getTopLevelItems());

        $this->em->clear();
        $this->controller()->edit($this->request('GET'), new Response(), ['id' => $menu->getId()]);
        $this->assertSame('admin/menus/edit.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['pages']);
        $this->assertCount(1, $this->rendered['data']['productions']);

        $tree = json_decode($this->rendered['data']['menuItemsJson'], true);
        $this->assertSame(
            ['About', 'News item', 'Hamlet', '2025-26', 'All Posts', '(untitled)'],
            array_column($tree, 'label')
        );
        $this->assertSame('Ext', $tree[0]['children'][0]['label']);
        $this->assertNull($tree[0]['children'][0]['sourceTitle']);
        $this->assertSame([false, false, false, false, false, true], array_column($tree, 'orphaned'));
    }

    public function testSaveTreeWithoutHtmxRedirects(): void
    {
        $menu = $this->menus->create(['name' => 'Main']);

        $result = $this->controller()->saveTree(
            $this->request('POST', ['name' => 'Main', 'items' => '[]']),
            new Response(),
            ['id' => $menu->getId()]
        );

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/admin/menus/edit/' . $menu->getId(), $result->getHeaderLine('Location'));
    }

    public function testMissingMenuReturns404(): void
    {
        $this->assertSame(404, $this->controller()->edit($this->request('GET'), new Response(), ['id' => 9])->getStatusCode());
        $this->assertSame(404, $this->controller()->saveTree($this->request('POST'), new Response(), ['id' => 9])->getStatusCode());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidTreeProvider(): array
    {
        $row = static fn(array $extra): string => json_encode([array_merge(['clientId' => 'a'], $extra)]);

        return [
            'blank name' => [['name' => ' '], 'Name is required.'],
            'taken location' => [['location' => 'taken'], 'That location is already assigned to another menu.'],
            'non-array items' => [['items' => '"x"'], 'Malformed menu item data.'],
            'row without client id' => [['items' => '[{"linkType":"custom"}]'], 'Malformed menu item data.'],
            'unknown link type' => [['items' => $row(['linkType' => 'nope'])], 'Invalid menu item link type.'],
            'custom without url' => [['items' => $row(['linkType' => 'custom'])], 'Custom links require a URL.'],
            'unparseable url' => [['items' => $row(['linkType' => 'custom', 'customUrl' => 'http://:80'])], 'Custom link URL is invalid.'],
            'ftp url' => [
                ['items' => $row(['linkType' => 'custom', 'customUrl' => 'ftp://x'])],
                'Custom link URLs must be relative or use http(s), mailto, or tel.',
            ],
            'protocol-relative url' => [
                ['items' => $row(['linkType' => 'custom', 'customUrl' => '//evil.test'])],
                'Protocol-relative URLs (starting with "//") are not allowed.',
            ],
            'javascript url on non-custom item' => [
                ['items' => $row(['linkType' => 'post', 'customUrl' => 'javascript:alert(1)'])],
                'Custom link URL scheme is not allowed.',
            ],
            'page without target' => [['items' => $row(['linkType' => 'page'])], 'Pages must link to a specific page.'],
            'unknown parent' => [
                ['items' => $row(['linkType' => 'post', 'parentClientId' => 'zzz'])],
                'Menu item references an unknown parent.',
            ],
            'cycle' => [
                ['items' => json_encode([
                    ['clientId' => 'a', 'parentClientId' => 'b', 'linkType' => 'post'],
                    ['clientId' => 'b', 'parentClientId' => 'a', 'linkType' => 'post'],
                ])],
                'Menu items cannot be nested in a cycle.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidTreeProvider')]
    public function testSaveTreeRejectsInvalidInput(array $body, string $message): void
    {
        $this->menus->create(['name' => 'Other', 'location' => 'taken']);
        $menu = $this->menus->create(['name' => 'Main']);
        $body += ['name' => 'Main'];

        $result = $this->controller()->saveTree($this->request('POST', $body), new Response(), ['id' => $menu->getId()]);
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame($message, (string) $result->getBody());

        $result = $this->controller()->saveTree($this->request('POST', $body, htmx: true), new Response(), ['id' => $menu->getId()]);
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame($message, $this->rendered['data']['message']);
    }

    public function testDestroyDeletesAndRendersListOrRedirects(): void
    {
        $a = $this->menus->create(['name' => 'A']);
        $b = $this->menus->create(['name' => 'B']);

        $this->controller()->destroy($this->request('DELETE', htmx: true), new Response(), ['id' => $a->getId()]);
        $this->assertSame('admin/menus/_list.html.twig', $this->rendered['template']);
        $this->assertCount(1, $this->rendered['data']['menus']);

        $result = $this->controller()->destroy($this->request('DELETE'), new Response(), ['id' => $b->getId()]);
        $this->assertSame('/admin/menus', $result->getHeaderLine('Location'));
        $this->assertSame([], $this->menus->fetchAllOrderedByName());
    }

    private function controller(): MenuController
    {
        return new MenuController(
            $this->menus,
            $this->em,
            $this->recordingTwig(),
            $this->locations,
            $this->resolver,
            new MenuLinkTargetOptionsService($this->pages, $this->posts, $this->productions, $this->seasons),
        );
    }
}
