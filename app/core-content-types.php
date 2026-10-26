<?php

// Core's built-in content types. Plugins and themes add their own with register_content_type().
// Names are singular; URL prefixes are given explicitly because they default to the name.
$coreContentTypes = [
    'season' => [
        'label'         => 'Seasons',
        'singularLabel' => 'Season',
        'urlPrefix'     => 'seasons',
        'controller'    => \TheatreCMS\Controllers\SeasonController::class,
    ],
    'production' => [
        'label' => 'Productions',
        'singularLabel' => 'Production',
        'urlPrefix' => 'productions',
        'controller'    => \TheatreCMS\Controllers\ProductionController::class,
    ],
    'person' => [
        'label' => 'People',
        'singularLabel' => 'Person',
        'urlPrefix' => 'people',
        'controller'    => \TheatreCMS\Controllers\PersonController::class,
    ],
    'work' => [
        'label' => 'Works',
        'singularLabel' => 'Work',
        'urlPrefix' => 'works',
        'controller'    => \TheatreCMS\Controllers\WorksController::class,
    ],
    'venue' => [
        'label' => 'Venues',
        'singularLabel' => 'Venue',
        'urlPrefix' => 'venues',
        'controller'    => \TheatreCMS\Controllers\VenueController::class,
    ],
    'event' => [
        'label' => 'Events',
        'singularLabel' => 'Event',
        'urlPrefix' => 'events',
        'controller'    => \TheatreCMS\Controllers\EventController::class,
    ],
    'sponsor' => [
        'label' => 'Sponsors',
        'singularLabel' => 'Sponsor',
        'urlPrefix' => 'sponsors',
        'controller'    => \TheatreCMS\Controllers\SponsorController::class,
    ],
    'post' => [
        'label' => 'Posts',
        'singularLabel' => 'Post',
        'urlPrefix' => 'posts',
        'controller'    => \TheatreCMS\Controllers\PostController::class,
    ],
];

foreach ($coreContentTypes as $name => $args) {
    register_content_type($name, $args);
}
