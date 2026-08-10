<?php

register_menu_location('primary', 'Primary Navigation');
register_menu_location('footer', 'Footer Menu');


add_filter('theme_head', function (string $headContent): string {
    $headContent .= "<meta name=\"foo\" content=\"bar\">" . PHP_EOL;
    return $headContent;
});