<?php

// Core's built-in content types. Plugins and themes add their own with register_content_type().
// Names are singular; URL prefixes are given explicitly because they default to the name.

register_content_type('season', [
    'label' => 'Seasons',
    'singularLabel' => 'Season',
    'urlPrefix' => 'seasons',
]);

register_content_type('production', [
    'label' => 'Productions',
    'singularLabel' => 'Production',
    'urlPrefix' => 'productions',
]);

register_content_type('person', [
    'label' => 'People',
    'singularLabel' => 'Person',
    'urlPrefix' => 'people',
]);

register_content_type('work', [
    'label' => 'Works',
    'singularLabel' => 'Work',
    'urlPrefix' => 'works',
]);

register_content_type('post', [
    'label' => 'Posts',
    'singularLabel' => 'Post',
    'urlPrefix' => 'posts',
]);
