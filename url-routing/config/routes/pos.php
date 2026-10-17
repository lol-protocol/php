<?php

declare(strict_types=1);

use App\Support\Router;

/**
 * POS site (Contrastocolor) — same dispatch principle as genealogy:
 * digit count of the first segment selects the type. The purchase flow
 * has no id to count, so it gets one-letter symbols instead of words,
 * picked to read the same in Spanish, English and most Latin-script
 * languages: c = carrito / cart / cesta, p = pago / pay / paiement.
 * Its steps are numeric actions, like everywhere else.
 */

return [
    'by_length' => [
        // An order's URL id is a random 16-digit code (ordenes.codigo), not
        // its row id: nothing else proves who placed it, so it must not be
        // guessable from a neighbour's.
        16 => [
            'type' => 'orden',
            'controller' => 'POS\OrderController',
            'actions' => [
                1 => 'invoice',
                2 => 'track',
                3 => 'devolucion',
            ],
        ],
        8 => [
            'type' => 'producto',
            'controller' => 'POS\ProductoController',
            'actions' => [
                1 => 'variantes',
                2 => 'atributos',
            ],
        ],
        7 => [
            'type' => 'coleccion',
            'controller' => 'POS\ColeccionController',
            'actions' => [],
        ],
        6 => [
            'type' => 'etiqueta',
            'controller' => 'POS\EtiquetaController',
            'actions' => [
                1 => 'productos',
            ],
        ],
        5 => [
            'type' => 'atributo',
            'controller' => 'POS\AtributoController',
            'actions' => [
                1 => 'productos',
            ],
        ],
        4 => [
            'type' => 'grupo',
            'controller' => 'POS\GrupoController',
            'actions' => [],
        ],
    ],

    // Lengths 1-3 are deliberately unused below grupo (4) — headroom for
    // future low-cardinality types without renumbering anything above.
    'reserved' => [
        '' => ['controller' => 'POS\HomeController', 'method' => 'index'],
        Router::ACCOUNT_SEGMENT => [
            'controller' => 'POS\CuentaController',
            'method' => 'index',
            'actions' => [
                1 => 'perfil',
                2 => 'ordenes',
                3 => 'deseos',
                4 => 'direcciones',
                5 => 'preferencias',
            ],
        ],
        // The cart's actions only take a POST (with a CSRF token).
        'c' => [
            'controller' => 'POS\CartController',
            'method' => 'show',
            'actions' => [
                1 => 'agregar',
                2 => 'actualizar',
                3 => 'quitar',
                4 => 'vaciar',
            ],
        ],
        'p' => [
            'controller' => 'POS\CheckoutController',
            'method' => 'index',
            'actions' => [
                1 => 'shipping',
                2 => 'payment',
                3 => 'confirm',
            ],
        ],
    ],

    // The purchase flow's old word URLs (GET only: a form posts to the new ones).
    'redirect' => [
        'cart' => '/c/',
        'checkout' => '/p/',
        'checkout/shipping' => '/p/1/',
        'checkout/payment' => '/p/2/',
        'checkout/confirm' => '/p/3/',
    ],
];
