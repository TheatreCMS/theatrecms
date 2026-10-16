<?php

use TheatreCMS\Auth\Capability;

// Core's taxonomies. Plugins and themes add their own with register_taxonomy().

register_taxonomy('genre', ['work'], [
    'label' => 'Genres',
    'singularLabel' => 'Genre',
    'capability' => Capability::MANAGE_PEOPLE,
]);
register_taxonomy('post_category', ['post'], [
    'label' => 'Categories',
    'singularLabel' => 'Category',
    'capability' => Capability::EDIT_POSTS,
    'urlPrefix' => 'category',
]);
