<?php

namespace TheatreCMS\Admin;

/**
 * One link in the admin sidebar.
 */
final class AdminMenuItem
{
    /**
     * @param string      $label      Link text
     * @param string      $url        Path relative to the site root, e.g. `admin/seasons`
     * @param string|null $capability Capability required to see the item; null means any signed-in user
     * @param string|null $group      Sidebar section label (e.g. `Content`); null for the top, ungrouped section
     * @param int         $position   Sort order within the group (lower first)
     * @param bool        $exact      Only highlight the item on its own URL, not on URLs below it
     */
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly ?string $capability = null,
        public readonly ?string $group = 'Content',
        public readonly int $position = 100,
        public readonly bool $exact = false,
    ) {
    }
}
