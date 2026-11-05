<?php

use TheatreCMS\Auth\Capability;

// Core's built-in content types. Plugins and themes add their own with register_content_type().
// Names are singular; URL prefixes are given explicitly because they default to the name.
$coreContentTypes = [
    'season' => [
        'label'         => 'Seasons',
        'singularLabel' => 'Season',
        'urlPrefix'     => 'seasons',
        'controller'    => \TheatreCMS\Controllers\SeasonController::class,
        'capability'    => Capability::MANAGE_SEASONS,
        'supports'      => ['editor', 'featured_image', 'hero_image'],
        'menuIcon'      => 'fa-solid fa-calendar-days',
    ],
    'production' => [
        'label' => 'Productions',
        'singularLabel' => 'Production',
        'urlPrefix' => 'productions',
        'controller'    => \TheatreCMS\Controllers\ProductionController::class,
        'capability'    => Capability::MANAGE_PRODUCTIONS,
        'supports'      => ['editor', 'excerpt', 'featured_image', 'hero_image'],
        'menuIcon'      => 'fa-solid fa-masks-theater',
    ],
    'person' => [
        'label' => 'People',
        'singularLabel' => 'Person',
        'urlPrefix' => 'people',
        'controller'    => \TheatreCMS\Controllers\PersonController::class,
        'capability'    => Capability::MANAGE_PEOPLE,
        'supports'      => ['editor', 'featured_image', 'hero_image'],
        'menuIcon'      => 'fa-solid fa-user',
    ],
    'work' => [
        'label' => 'Works',
        'singularLabel' => 'Work',
        'urlPrefix' => 'works',
        'controller'    => \TheatreCMS\Controllers\WorksController::class,
        'capability'    => Capability::MANAGE_WORKS,
        'menuIcon'      => 'fa-solid fa-scroll',
    ],
    'venue' => [
        'label' => 'Venues',
        'singularLabel' => 'Venue',
        'urlPrefix' => 'venues',
        'controller'    => \TheatreCMS\Controllers\VenueController::class,
        'capability'    => Capability::MANAGE_VENUES,
        'supports'      => ['editor', 'featured_image', 'hero_image'],
        'menuIcon'      => 'fa-solid fa-location-dot',
    ],
    'event' => [
        'label' => 'Events',
        'singularLabel' => 'Event',
        'urlPrefix' => 'events',
        'controller'    => \TheatreCMS\Controllers\EventController::class,
        'capability'    => Capability::MANAGE_EVENTS,
        'menuIcon'      => 'fa-solid fa-ticket',
    ],
    'sponsor' => [
        'label' => 'Sponsors',
        'singularLabel' => 'Sponsor',
        'urlPrefix' => 'sponsors',
        'controller'    => \TheatreCMS\Controllers\SponsorController::class,
        'capability'    => Capability::MANAGE_SPONSORS,
        // Sponsors appear only on the seasons and productions they back, never on pages of their own.
        'public'        => false,
        'menuIcon'      => 'fa-solid fa-handshake',
    ],
    // Posts use the generic EntryController and live in the entries table: the reference
    // type for everything a registered content type can do.
    'post' => [
        'label'         => 'Posts',
        'singularLabel' => 'Post',
        'urlPrefix'     => 'posts',
        'capability'    => Capability::MANAGE_POSTS,
        'supports'      => ['editor', 'excerpt', 'featured_image', 'hero_image'],
        'menuIcon'      => 'fa-solid fa-newspaper',
        'ogType'        => 'article',
    ],
    // Served at the site root and nested by parent: /about, /about/board-of-directors.
    'page' => [
        'label'         => 'Pages',
        'singularLabel' => 'Page',
        'urlPrefix'     => '',
        'hasArchive'    => false,
        'hierarchical'  => true,
        'controller'    => \TheatreCMS\Controllers\PageController::class,
        'capability'    => Capability::MANAGE_PAGES,
        'menuIcon'      => 'fa-solid fa-file-lines',
    ],
];

foreach ($coreContentTypes as $name => $args) {
    register_content_type($name, $args);
}
