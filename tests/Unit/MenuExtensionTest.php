<?php

namespace TheatreCMS\Tests\Unit;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TheatreCMS\Menus\MenuItemResolver;
use TheatreCMS\Models\MenuItem;
use TheatreCMS\Repositories\MenuRepository;
use TheatreCMS\Tests\Includes\UsesSqliteEntityManager;
use TheatreCMS\Twig\MenuExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

#[AllowMockObjectsWithoutExpectations]
class MenuExtensionTest extends TestCase
{
    use UsesSqliteEntityManager;

    private EntityManager $em;
    private MenuRepository $menus;
    private MenuExtension $extension;

    protected function setUp(): void
    {
        $this->em = $this->createSqliteEntityManager();
        $this->menus = new MenuRepository($this->em);

        $resolver = $this->createMock(MenuItemResolver::class);
        $resolver->method('resolveUrl')->willReturnCallback(fn(MenuItem $item) => $item->getCustomUrl());
        $resolver->method('resolveLabel')->willReturnCallback(fn(MenuItem $item) => (string) $item->getLabel());

        $this->extension = new MenuExtension($this->menus, $resolver);
    }

    public function testRegistersRenderAndGetFunctions(): void
    {
        $names = array_map(fn($f) => $f->getName(), $this->extension->getFunctions());

        $this->assertSame(['render_menu', 'get_menu'], $names);
    }

    public function testGetMenuTreeReturnsNullForUnassignedLocation(): void
    {
        $this->assertNull($this->extension->getMenuTree('primary'));
        $this->assertSame('', (string) $this->extension->renderMenu($this->twig(), 'primary'));
    }

    public function testGetMenuTreeNestsChildrenAndSkipsUnresolvableItems(): void
    {
        $this->menuWithItems();

        $this->assertSame([
            ['label' => 'Home', 'url' => '/', 'current' => false, 'currentAncestor' => false, 'children' => [
                ['label' => 'Child', 'url' => '/child', 'current' => false, 'currentAncestor' => false, 'children' => []],
            ]],
        ], $this->extension->getMenuTree('primary'));
    }

    public function testGetMenuTreeMarksTheCurrentItemAndItsAncestors(): void
    {
        $this->menuWithItems();

        $tree = $this->extension->getMenuTree('primary', '/child/');

        $this->assertFalse($tree[0]['current']);
        $this->assertTrue($tree[0]['currentAncestor']);
        $this->assertTrue($tree[0]['children'][0]['current']);
        $this->assertFalse($tree[0]['children'][0]['currentAncestor']);

        $tree = $this->extension->getMenuTree('primary', '/');

        $this->assertTrue($tree[0]['current']);
        $this->assertFalse($tree[0]['currentAncestor']);
        $this->assertFalse($tree[0]['children'][0]['current']);
    }

    public function testOnlySiteRelativeUrlsCanBeCurrent(): void
    {
        $menu = $this->menus->create(['name' => 'Main', 'location' => 'primary']);
        $this->menus->saveTree($menu, 'Main', 'primary', [
            ['clientId' => 'a', 'parentClientId' => null, 'position' => 0, 'label' => 'Tickets', 'linkType' => 'custom', 'targetId' => null, 'customUrl' => 'https://tickets.example.com/'],
            ['clientId' => 'b', 'parentClientId' => null, 'position' => 1, 'label' => 'Faq', 'linkType' => 'custom', 'targetId' => null, 'customUrl' => '/faq?tab=1#top'],
        ]);
        $this->em->clear();

        $tree = $this->extension->getMenuTree('primary', '/');
        $this->assertFalse($tree[0]['current']);

        $tree = $this->extension->getMenuTree('primary', '/faq');
        $this->assertTrue($tree[1]['current']);
    }

    public function testRenderMenuReadsTheCurrentPathFromTheTemplateContext(): void
    {
        $this->menuWithItems();

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addExtension($this->extension);
        $html = $twig->createTemplate("{{ render_menu('primary') }}")->render(['current_path' => '/child']);

        $this->assertMatchesRegularExpression('/class="menu-item menu-item--has-children menu-item--current-ancestor"/', $html);
        $this->assertStringContainsString('<li class="menu-item menu-item--current">', $html);
        $this->assertStringContainsString('<a href="/child" aria-current="page">Child</a>', $html);
        $this->assertSame(1, substr_count($html, 'aria-current'));
    }

    public function testRenderMenuRendersPartialWithTreeAndOptions(): void
    {
        $this->menuWithItems();

        $html = (string) $this->extension->renderMenu($this->twig(), 'primary', ['class' => 'nav']);

        $this->assertSame('nav:Home(Child)', $html);
    }

    private function menuWithItems(): void
    {
        $menu = $this->menus->create(['name' => 'Main', 'location' => 'primary']);
        $this->menus->saveTree($menu, 'Main', 'primary', [
            ['clientId' => 'a', 'parentClientId' => null, 'position' => 0, 'label' => 'Home', 'linkType' => 'custom', 'targetId' => null, 'customUrl' => '/'],
            ['clientId' => 'b', 'parentClientId' => 'a', 'position' => 0, 'label' => 'Child', 'linkType' => 'custom', 'targetId' => null, 'customUrl' => '/child'],
            ['clientId' => 'c', 'parentClientId' => null, 'position' => 1, 'label' => 'Gone', 'linkType' => 'page', 'targetId' => 9, 'customUrl' => null],
        ]);
        // Children are the inverse side of the parent relation, so reload as a fresh request would.
        $this->em->clear();
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'partials/_menu_items.html.twig' => '{{ options.class }}:{% for i in items %}{{ i.label }}'
                . '({% for c in i.children %}{{ c.label }}{% endfor %}){% endfor %}',
        ]));
    }
}
