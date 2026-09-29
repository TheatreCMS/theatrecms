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
            ['label' => 'Home', 'url' => '/', 'children' => [
                ['label' => 'Child', 'url' => '/child', 'children' => []],
            ]],
        ], $this->extension->getMenuTree('primary'));
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
