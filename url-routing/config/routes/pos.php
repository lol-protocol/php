<?php

declare(strict_types=1);

use App\Support\Router;

/**
 * POS site (Contrastocolor) — same dispatch principle as genealogy:
 * digit count of the first segment selects the type. The purchase
 * flow (cart/checkout/order) is the one deliberate exception and
 * keeps short literal English words instead of a numeric code.
 */

return [
    'by_length' => [
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

    'literal' => [
        'cart' => ['controller' => 'POS\CartController', 'method' => 'show'],
        'checkout' => ['controller' => 'POS\CheckoutController', 'method' => 'index'],
        'checkout/shipping' => ['controller' => 'POS\CheckoutController', 'method' => 'shipping'],
        'checkout/payment' => ['controller' => 'POS\CheckoutController', 'method' => 'payment'],
        'checkout/confirm' => ['controller' => 'POS\CheckoutController', 'method' => 'confirm'],
    ],

    'order' => [
        'controller' => 'POS\OrderController',
        'actions' => [
            1 => 'invoice',
            2 => 'track',
            3 => 'devolucion',
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
    ],
];
