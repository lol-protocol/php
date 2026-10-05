<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';

$grupos = [
    'presets' => [
        ['key' => 'latam', 'label' => 'LATAM', 'countries' => ['AR', 'BR']],
    ],
    'countries' => ['AR' => 'Argentina', 'DE' => 'Alemania'],
];

// "Todos los países" es texto de interfaz, no dato: el backend no lo arma en un
// idioma (el frontend pone el suyo, traducido). null = "sin etiqueta específica",
// justo cuando api_resolve_scope_countries() tampoco aplica filtro de país.
assert_igual(null, api_scope_label('all', $grupos), 'scope_label: all -> null (el frontend pone "todos los países" traducido)');
assert_igual(null, api_scope_label('', $grupos), 'scope_label: scope vacío -> null');
assert_igual('LATAM', api_scope_label('preset:latam', $grupos), 'scope_label: preset conocido -> su etiqueta');
assert_igual(null, api_scope_label('preset:borrado', $grupos), 'scope_label: preset desconocido -> null, ni un texto en español ni el string crudo');
assert_igual('Argentina', api_scope_label('country:AR', $grupos), 'scope_label: país conocido -> su nombre');
assert_igual('XX', api_scope_label('country:XX', $grupos), 'scope_label: país desconocido -> el código');
assert_igual(null, api_scope_label('cualquier-cosa', $grupos), 'scope_label: formato no reconocido -> null (el resolvedor también lo trata como "todos"), no el string crudo');

// Coherencia con el resolvedor: hay etiqueta si y solo si hay filtro de país.
foreach (['all', '', 'preset:latam', 'preset:borrado', 'country:AR', 'country:XX', 'cualquier-cosa'] as $scope) {
    assert_igual(
        api_resolve_scope_countries($scope, $grupos) === null,
        api_scope_label($scope, $grupos) === null,
        "scope_label: '$scope' queda sin etiqueta exactamente cuando no hay filtro de país"
    );
}
