<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/Universo.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';
require_once __DIR__ . '/../../servidor-php/codigo/api/linea-tiempo.php';

// El universo de comparación (contra quién se compara una acción: países, edad, género y el usuario que se mira, que
// queda afuera) viajaba como cinco argumentos sueltos desde el endpoint hasta el pedido al servicio de estadísticas.
// Ahora es un objeto: acá, qué le pide al servicio y cómo sale de la query del timeline.

// Universo::aQuery(): lo que el servicio de estadísticas recibe sobre el universo (sin el tipo de acción, que es de cada pedido).
$todos = new Universo(null, 0, 150, 'all', null);
assert_igual(
    ['age_min' => 0, 'age_max' => 150, 'gender' => 'all'],
    $todos->aQuery(),
    'universo: sin filtro de país ni usuario excluido, solo la edad y el género (sin "countries" ni "exclude")'
);
$acotado = new Universo(['AR', 'BR'], 20, 50, 'F', 'u007');
assert_igual(
    ['age_min' => 20, 'age_max' => 50, 'gender' => 'F', 'countries' => 'AR,BR', 'exclude' => 'u007'],
    $acotado->aQuery(),
    'universo: los países van juntos con coma y el usuario excluido como "exclude"'
);
assert_igual(['AR', 'BR'], $acotado->paises, 'universo: los países quedan como se pasaron');
assert_igual('u007', $acotado->excluirUsuario, 'universo: el usuario excluido queda como se pasó');

// api_universo_desde_query(): lo que sale de la query del timeline (?scope=&age_min=&age_max=&gender=) y del usuario que se mira.
$grupos = [
    'presets' => [['key' => 'latam', 'label' => 'LATAM', 'countries' => ['AR', 'BR', 'CL']]],
    'countries' => ['AR' => 'Argentina'],
];
$porDefecto = api_universo_desde_query([], $grupos, 'u001');
assert_igual(
    [null, 0, 150, 'all', 'u001'],
    [$porDefecto->paises, $porDefecto->edadMin, $porDefecto->edadMax, $porDefecto->genero, $porDefecto->excluirUsuario],
    'universo desde la query: sin nada, todos los países, edad 0-150, todos los géneros, sin el usuario que se mira'
);

$completo = api_universo_desde_query(['scope' => 'preset:latam', 'age_min' => '25', 'age_max' => '40', 'gender' => 'M'], $grupos, 'u002');
assert_igual(
    [['AR', 'BR', 'CL'], 25, 40, 'M', 'u002'],
    [$completo->paises, $completo->edadMin, $completo->edadMax, $completo->genero, $completo->excluirUsuario],
    'universo desde la query: un grupo de países, la edad como enteros, el género y el usuario excluido'
);

foreach (
    [
        'country:AR' => ['AR'],
        'all' => null,
        '' => null,
        'preset:inexistente' => null, // un grupo que se borró después de guardar un filtro con él: sin filtro de país
        'cualquier-cosa' => null,
    ] as $scope => $paises
) {
    assert_igual(
        $paises,
        api_universo_desde_query(['scope' => $scope], $grupos, 'u001')->paises,
        'universo desde la query: scope ' . var_export($scope, true) . ' -> ' . json_encode($paises)
    );
}
