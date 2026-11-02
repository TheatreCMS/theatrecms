<?php

// Core's built-in content types. Plugins and themes add their own with register_content_type().
// Names are singular; URL prefixes are given explicitly because they default to the name.
$coreContentTypes = [
    'season' => [
        'label'         => 'Seasons',
        'singularLabel' => 'Season',
        'urlPrefix'     => 'seasons',
        'controller'    => \TheatreCMS\Controllers\SeasonController::class,
        'menuIcon'      => 'fa-solid fa-calendar-days',
    ],
    'production' => [
        'label' => 'Productions',
        'singularLabel' => 'Production',
        'urlPrefix' => 'productions',
        'controller'    => \TheatreCMS\Controllers\ProductionController::class,
        'menuIcon'      => 'fa-solid fa-masks-theater',
    ],
    'person' => [
        'label' => 'People',
        'singularLabel' => 'Person',
        'urlPrefix' => 'people',
        'controller'    => \TheatreCMS\Controllers\PersonController::class,
        'menuIcon'      => 'fa-solid fa-user',
    ],
    'work' => [
        'label' => 'Works',
        'singularLabel' => 'Work',
        'urlPrefix' => 'works',
        'controller'    => \TheatreCMS\Controllers\WorksController::class,
        'menuIcon'      => 'fa-solid fa-scroll',
    ],
    'venue' => [
        'label' => 'Venues',
        'singularLabel' => 'Venue',
        'urlPrefix' => 'venues',
        'controller'    => \TheatreCMS\Controllers\VenueController::class,
        'menuIcon'      => 'fa-solid fa-location-dot',
    ],
    'event' => [
        'label' => 'Events',
        'singularLabel' => 'Event',
        'urlPrefix' => 'events',
        'controller'    => \TheatreCMS\Controllers\EventController::class,
        'menuIcon'      => 'fa-solid fa-ticket',
    ],
    'sponsor' => [
        'label' => 'Sponsors',
        'singularLabel' => 'Sponsor',
        'urlPrefix' => 'sponsors',
        'controller'    => \TheatreCMS\Controllers\SponsorController::class,
        'menuIcon'      => 'fa-solid fa-handshake',
    ],
    'post' => [
        'label' => 'Posts',
        'singularLabel' => 'Post',
        'urlPrefix' => 'posts',
        'controller'    => \TheatreCMS\Controllers\PostController::class,
        'menuIcon'      => 'fa-solid fa-newspaper',
    ],
];

foreach ($coreContentTypes as $name => $args) {
    register_content_type($name, $args);
}
