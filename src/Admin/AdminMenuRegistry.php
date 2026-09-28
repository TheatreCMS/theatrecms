<?php

namespace TheatreCMS\Admin;

/**
 * The admin sidebar: sections (groups) of links, populated by core in `app/admin-menu.php`
 * and by plugins through `PluginInterface::adminMenuItems()` or `register_admin_menu_item()`.
 * Rendered by `templates/layouts/admin.html.twig` via the `admin_menu()` Twig function.
 */
class AdminMenuRegistry
{
    /**
     * Groups without an explicit position sort here: after core's content sections and before
     * the Administration section.
     */
    public const DEFAULT_GROUP_POSITION = 50;

    /**
     * @var array<string, int> group label ('' for the ungrouped top section) => position
     */
    private array $groupPositions = [];

    /**
     * @var AdminMenuItem[]
     */
    private array $items = [];

    private static ?self $instance = null;

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('The AdminMenuRegistry has not been initialized.');
        }

        return self::$instance;
    }

    public function registerGroup(?string $label, int $position): void
    {
        $this->groupPositions[$label ?? ''] = $position;
    }

    public function add(AdminMenuItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return array<int, array{label: ?string, items: AdminMenuItem[]}> sections in display order,
     *         each with its items sorted by position (ties keep registration order)
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->items as $index => $item) {
            $groups[$item->group ?? ''][$index] = $item;
        }

        $order = array_keys($groups);
        usort($order, fn(string $a, string $b): int => $this->groupPosition($a) <=> $this->groupPosition($b));

        $result = [];
        foreach ($order as $label) {
            $items = $groups[$label];
            uksort($items, static fn(int $a, int $b): int => [$items[$a]->position, $a] <=> [$items[$b]->position, $b]);
            $result[] = ['label' => $label === '' ? null : $label, 'items' => array_values($items)];
        }

        return $result;
    }

    private function groupPosition(string $label): int
    {
        return $this->groupPositions[$label] ?? self::DEFAULT_GROUP_POSITION;
    }
}
