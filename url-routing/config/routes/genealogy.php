<?php

declare(strict_types=1);

use App\Support\Router;

/**
 * Genealogy site — resource type is inferred from the shape of the
 * first path segment, never from words:
 *
 *   - digits only  -> digit count selects the type (see `by_length`)
 *   - letters only -> place hierarchy, pais/region/ciudad (see `place`)
 *   - exact match  -> fixed system paths (see `reserved`)
 *
 * Digit count scales with each type's expected number of records —
 * persona is the largest catalog, organizacion the smallest.
 */

return [
    'by_length' => [
        10 => [
            'type' => 'persona',
            'controller' => 'Genealogy\PersonaController',
            'actions' => [
                1 => 'ascendencia',
                2 => 'descendencia',
                3 => 'vinculos',
                4 => 'cronologia',
            ],
        ],
        9 => [
            'type' => 'suceso',
            'controller' => 'Genealogy\SucesoController',
            'actions' => [],
        ],
        8 => [
            'type' => 'registro',
            'controller' => 'Genealogy\RegistroController',
            'actions' => [
                1 => 'fuente',
            ],
        ],
        7 => [
            'type' => 'coleccion',
            'controller' => 'Genealogy\ColeccionController',
            'actions' => [
                1 => 'vista',
                2 => 'editar',
                3 => 'exportar',
            ],
        ],
        6 => [
            'type' => 'grupo',
            'controller' => 'Genealogy\GrupoController',
            'actions' => [
                1 => 'red',
                2 => 'dispersion',
            ],
        ],
        5 => [
            'type' => 'organizacion',
            'controller' => 'Genealogy\OrganizacionController',
            'actions' => [
                1 => 'miembros',
                2 => 'registros',
            ],
        ],
    ],

    'place' => [
        'controller' => 'Genealogy\LugarController',
        'actions' => [
            1 => 'personas',
            2 => 'sucesos',
        ],
    ],

    // Lengths 1-4 are deliberately unused below organizacion (5) — headroom
    // for future low-cardinality types without renumbering anything above.
    'reserved' => [
        '' => ['controller' => 'Genealogy\HomeController', 'method' => 'index'],
        Router::ACCOUNT_SEGMENT => [
            'controller' => 'Genealogy\CuentaController',
            'method' => 'index',
            'actions' => [
                1 => 'colecciones',
                2 => 'aportes',
            ],
        ],
    ],
];
