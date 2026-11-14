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
                5 => 'viva',
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

    // For crawlers: what may be indexed, and every page a visitor may see.
    'archivos' => [
        'robots.txt' => ['controller' => 'Genealogy\ArchivosController', 'method' => 'robots'],
        'sitemap.xml' => ['controller' => 'Genealogy\ArchivosController', 'method' => 'indice'],
        'sitemap' => ['controller' => 'Genealogy\ArchivosController', 'method' => 'parte'],
    ],

    // Lengths 1-4 are deliberately unused below organizacion (5) — headroom
    // for future low-cardinality types without renumbering anything above.
    // `noindex` => true: a functional page that sends `X-Robots-Tag: noindex` (robots.txt only keeps crawlers
    // from walking it; it does not keep a URL that someone links from showing up in a search engine).
    'reserved' => [
        '' => ['controller' => 'Genealogy\HomeController', 'method' => 'index'],
        // Search: /b/{digit width of the type}/[{text}/][{page}/].
        'b' => ['controller' => 'Genealogy\HomeController', 'method' => 'buscar', 'ruta' => 3, 'noindex' => true],
        // Asking to withdraw a page: /r/{id}/, open to anyone (see RetiroController).
        'r' => ['controller' => 'Genealogy\RetiroController', 'method' => 'pedir', 'ruta' => 1, 'noindex' => true],
        // Asking what the site holds about a persona, to correct it or to erase it: /s/{id}/, open to anyone (see SolicitudController).
        's' => ['controller' => 'Genealogy\SolicitudController', 'method' => 'pedir', 'ruta' => 1, 'noindex' => true],
        // Reading, with a key, what the site holds about a persona: /e/{token}/ (see EntregaController; the owner makes the link).
        'e' => ['controller' => 'Genealogy\EntregaController', 'method' => 'ver', 'ruta' => 1, 'noindex' => true],
        Router::ACCOUNT_SEGMENT => [
            'controller' => 'Genealogy\CuentaController',
            'method' => 'index',
            'noindex' => true,
            'actions' => [
                1 => 'colecciones',
                2 => 'aportes',
                3 => 'retiros',
                4 => 'solicitudes',
            ],
        ],
    ],

    // A page with a pedido de retiro the owner hid or withdrew answers 410 to visitors (Support/Retiros.php).
    'retiros' => true,
];
