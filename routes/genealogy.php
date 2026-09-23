<?php

/**
 * Genealogy Site Routes
 * URL patterns for the genealogy module
 */

return [
    // People / Individuals
    'people.index' => [
        'path' => '/people/',
        'controller' => 'Genealogy\PeopleController@index',
        'methods' => ['GET'],
    ],
    'people.show' => [
        'path' => '/people/{id}/',
        'controller' => 'Genealogy\PeopleController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'people.ancestors' => [
        'path' => '/people/{id}/ancestors/',
        'controller' => 'Genealogy\PeopleController@ancestors',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'people.descendants' => [
        'path' => '/people/{id}/descendants/',
        'controller' => 'Genealogy\PeopleController@descendants',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'people.relationships' => [
        'path' => '/people/{id}/relationships/',
        'controller' => 'Genealogy\PeopleController@relationships',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'people.timeline' => [
        'path' => '/people/{id}/timeline/',
        'controller' => 'Genealogy\PeopleController@timeline',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],

    // Surnames / Families
    'surnames.index' => [
        'path' => '/surnames/',
        'controller' => 'Genealogy\SurnamesController@index',
        'methods' => ['GET'],
    ],
    'surnames.show' => [
        'path' => '/surnames/{surname}/',
        'controller' => 'Genealogy\SurnamesController@show',
        'methods' => ['GET'],
        'constraints' => ['surname' => '[a-z-]+'],
    ],
    'surnames.tree' => [
        'path' => '/surnames/{surname}/tree/',
        'controller' => 'Genealogy\SurnamesController@tree',
        'methods' => ['GET'],
        'constraints' => ['surname' => '[a-z-]+'],
    ],
    'surnames.distribution' => [
        'path' => '/surnames/{surname}/distribution/',
        'controller' => 'Genealogy\SurnamesController@distribution',
        'methods' => ['GET'],
        'constraints' => ['surname' => '[a-z-]+'],
    ],
    'families.show' => [
        'path' => '/families/{id}/',
        'controller' => 'Genealogy\FamiliesController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'families.genealogy' => [
        'path' => '/families/{id}/genealogy/',
        'controller' => 'Genealogy\FamiliesController@genealogy',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'families.members' => [
        'path' => '/families/{id}/members/',
        'controller' => 'Genealogy\FamiliesController@members',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],

    // Places / Locations
    'places.index' => [
        'path' => '/places/',
        'controller' => 'Genealogy\PlacesController@index',
        'methods' => ['GET'],
    ],
    'places.country' => [
        'path' => '/places/{country}/',
        'controller' => 'Genealogy\PlacesController@country',
        'methods' => ['GET'],
        'constraints' => ['country' => '[a-z-]+'],
    ],
    'places.state' => [
        'path' => '/places/{country}/{state}/',
        'controller' => 'Genealogy\PlacesController@state',
        'methods' => ['GET'],
        'constraints' => ['country' => '[a-z-]+', 'state' => '[a-z-]+'],
    ],
    'places.city' => [
        'path' => '/places/{country}/{state}/{city}/',
        'controller' => 'Genealogy\PlacesController@city',
        'methods' => ['GET'],
        'constraints' => ['country' => '[a-z-]+', 'state' => '[a-z-]+', 'city' => '[a-z-]+'],
    ],
    'places.people' => [
        'path' => '/places/{id}/people/',
        'controller' => 'Genealogy\PlacesController@people',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'places.events' => [
        'path' => '/places/{id}/events/',
        'controller' => 'Genealogy\PlacesController@events',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],

    // Organizations
    'organizations.index' => [
        'path' => '/organizations/',
        'controller' => 'Genealogy\OrganizationsController@index',
        'methods' => ['GET'],
    ],
    'organizations.show' => [
        'path' => '/organizations/{id}/',
        'controller' => 'Genealogy\OrganizationsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'organizations.members' => [
        'path' => '/organizations/{id}/members/',
        'controller' => 'Genealogy\OrganizationsController@members',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'organizations.records' => [
        'path' => '/organizations/{id}/records/',
        'controller' => 'Genealogy\OrganizationsController@records',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],

    // Events
    'events.index' => [
        'path' => '/events/',
        'controller' => 'Genealogy\EventsController@index',
        'methods' => ['GET'],
    ],
    'events.births' => [
        'path' => '/events/births/',
        'controller' => 'Genealogy\EventsController@births',
        'methods' => ['GET'],
    ],
    'events.deaths' => [
        'path' => '/events/deaths/',
        'controller' => 'Genealogy\EventsController@deaths',
        'methods' => ['GET'],
    ],
    'events.marriages' => [
        'path' => '/events/marriages/',
        'controller' => 'Genealogy\EventsController@marriages',
        'methods' => ['GET'],
    ],
    'events.migrations' => [
        'path' => '/events/migrations/',
        'controller' => 'Genealogy\EventsController@migrations',
        'methods' => ['GET'],
    ],
    'events.show' => [
        'path' => '/events/{id}/',
        'controller' => 'Genealogy\EventsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],

    // Records / Documents
    'records.index' => [
        'path' => '/records/',
        'controller' => 'Genealogy\RecordsController@index',
        'methods' => ['GET'],
    ],
    'records.type' => [
        'path' => '/records/{type}/',
        'controller' => 'Genealogy\RecordsController@byType',
        'methods' => ['GET'],
        'constraints' => ['type' => '[a-z]+'],
    ],
    'records.show' => [
        'path' => '/records/{id}/',
        'controller' => 'Genealogy\RecordsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'records.source' => [
        'path' => '/records/{id}/source/',
        'controller' => 'Genealogy\RecordsController@source',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],

    // Search
    'genealogy.search' => [
        'path' => '/search/',
        'controller' => 'Genealogy\SearchController@index',
        'methods' => ['GET'],
    ],
    'genealogy.search.people' => [
        'path' => '/search/people/',
        'controller' => 'Genealogy\SearchController@people',
        'methods' => ['GET'],
    ],
    'genealogy.search.surnames' => [
        'path' => '/search/surnames/',
        'controller' => 'Genealogy\SearchController@surnames',
        'methods' => ['GET'],
    ],
    'genealogy.search.places' => [
        'path' => '/search/places/',
        'controller' => 'Genealogy\SearchController@places',
        'methods' => ['GET'],
    ],

    // Trees
    'trees.index' => [
        'path' => '/trees/',
        'controller' => 'Genealogy\TreesController@index',
        'methods' => ['GET'],
    ],
    'trees.show' => [
        'path' => '/trees/{id}/',
        'controller' => 'Genealogy\TreesController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'trees.view' => [
        'path' => '/trees/{id}/view/',
        'controller' => 'Genealogy\TreesController@view',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'trees.edit' => [
        'path' => '/trees/{id}/edit/',
        'controller' => 'Genealogy\TreesController@edit',
        'methods' => ['GET', 'POST'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'trees.export' => [
        'path' => '/trees/{id}/export/',
        'controller' => 'Genealogy\TreesController@export',
        'methods' => ['GET', 'POST'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],

    // User Profile
    'genealogy.profile' => [
        'path' => '/profile/',
        'controller' => 'Genealogy\ProfileController@show',
        'methods' => ['GET'],
    ],
    'genealogy.profile.trees' => [
        'path' => '/profile/trees/',
        'controller' => 'Genealogy\ProfileController@trees',
        'methods' => ['GET'],
    ],
    'genealogy.profile.contributions' => [
        'path' => '/profile/contributions/',
        'controller' => 'Genealogy\ProfileController@contributions',
        'methods' => ['GET'],
    ],
    'genealogy.profile.saved' => [
        'path' => '/profile/saved/',
        'controller' => 'Genealogy\ProfileController@saved',
        'methods' => ['GET'],
    ],
    'genealogy.settings' => [
        'path' => '/settings/',
        'controller' => 'Genealogy\SettingsController@show',
        'methods' => ['GET', 'POST'],
    ],

    // Reports
    'genealogy.reports' => [
        'path' => '/reports/',
        'controller' => 'Genealogy\ReportsController@index',
        'methods' => ['GET'],
    ],
    'genealogy.reports.statistics' => [
        'path' => '/reports/statistics/',
        'controller' => 'Genealogy\ReportsController@statistics',
        'methods' => ['GET'],
    ],
    'genealogy.reports.surnames' => [
        'path' => '/reports/surnames-distribution/',
        'controller' => 'Genealogy\ReportsController@surnamesDistribution',
        'methods' => ['GET'],
    ],
    'genealogy.reports.timeline' => [
        'path' => '/reports/timeline/{year}/',
        'controller' => 'Genealogy\ReportsController@timeline',
        'methods' => ['GET'],
        'constraints' => ['year' => '[0-9]{4}'],
    ],
];
