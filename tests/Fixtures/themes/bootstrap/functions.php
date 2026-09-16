<?php

use TheatreCMS\Auth\CapabilityRegistry;

$GLOBALS['theme_functions_load_count'] = ($GLOBALS['theme_functions_load_count'] ?? 0) + 1;

add_filter('theatrecms/bootstrap-test', static fn(string $value): string => $value . '-theme');
register_menu_location('bootstrap-test', 'Bootstrap Test');
register_image_size('theme-card', 640, 360, true);
CapabilityRegistry::getInstance()->register(999, ['theme-bootstrap-test']);
