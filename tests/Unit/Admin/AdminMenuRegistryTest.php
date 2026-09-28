<?php

namespace TheatreCMS\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use TheatreCMS\Admin\AdminMenuItem;
use TheatreCMS\Admin\AdminMenuRegistry;

/**
 * @coversDefaultClass \TheatreCMS\Admin\AdminMenuRegistry
 */
class AdminMenuRegistryTest extends TestCase
{
    public function testGroupsAreOrderedByPositionWithUnpositionedGroupsBeforeAdministration(): void
    {
        $registry = new AdminMenuRegistry();
        $registry->registerGroup(null, 0);
        $registry->registerGroup('Content', 10);
        $registry->registerGroup('Administration', 90);

        $registry->add(new AdminMenuItem('Users', 'admin/users', null, 'Administration'));
        $registry->add(new AdminMenuItem('Forms', 'admin/forms', null, 'Forms'));
        $registry->add(new AdminMenuItem('Posts', 'admin/posts', null, 'Content'));
        $registry->add(new AdminMenuItem('Dashboard', 'admin', null, null));

        $this->assertSame([null, 'Content', 'Forms', 'Administration'], array_column($registry->groups(), 'label'));
    }

    public function testItemsSortByPositionAndKeepRegistrationOrderOnTies(): void
    {
        $registry = new AdminMenuRegistry();
        $registry->add(new AdminMenuItem('Media', 'admin/media', null, 'Content', 100));
        $registry->add(new AdminMenuItem('Seasons', 'admin/seasons', null, 'Content', 10));
        $registry->add(new AdminMenuItem('News', 'admin/news', null, 'Content', 100));

        $labels = array_map(static fn(AdminMenuItem $item) => $item->label, $registry->groups()[0]['items']);

        $this->assertSame(['Seasons', 'Media', 'News'], $labels);
    }

    public function testCoreItemsFileRegistersTheSidebar(): void
    {
        $registry = new AdminMenuRegistry();
        AdminMenuRegistry::setInstance($registry);

        require dirname(__DIR__, 3) . '/app/admin-menu-items.php';

        $groups = $registry->groups();
        $this->assertSame([null, 'Content', 'Appearance', 'Administration'], array_column($groups, 'label'));
        $this->assertSame('Dashboard', $groups[0]['items'][0]->label);
        $this->assertTrue($groups[0]['items'][0]->exact);
        $this->assertSame('admin/seasons', $groups[1]['items'][0]->url);
    }

    public function testGetInstanceThrowsWhenNotInitialized(): void
    {
        $reflection = new \ReflectionProperty(AdminMenuRegistry::class, 'instance');
        $reflection->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        AdminMenuRegistry::getInstance();
    }
}
