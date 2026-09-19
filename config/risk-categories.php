<?php

return [
    'burlesco' => [
        'name' => 'Burlesco',
        'description' => 'Términos usados para burlarse o ridiculizar',
        'severity' => 'medium',
        'examples' => ['vejestorio', 'momia', 'aniñado'],
    ],
    'animal' => [
        'name' => 'Animal',
        'description' => 'Comparaciones con animales de forma despectiva',
        'severity' => 'medium',
        'examples' => ['cerda', 'cerdo', 'asno', 'burro'],
    ],
    'ordinario' => [
        'name' => 'Ordinario',
        'description' => 'Palabras vulgares u ordinarias',
        'severity' => 'high',
        'examples' => ['mierda', 'caca', 'pis', 'culo'],
    ],
    'moral' => [
        'name' => 'Moral',
        'description' => 'Insultos sobre moralidad o comportamiento',
        'severity' => 'high',
        'examples' => ['bastardo', 'canalla', 'mentiroso'],
    ],
    'intelectual' => [
        'name' => 'Intelectual',
        'description' => 'Insultos sobre inteligencia o capacidad mental',
        'severity' => 'medium',
        'examples' => ['idiota', 'estúpido', 'ignorante'],
    ],
    'fisico' => [
        'name' => 'Físico',
        'description' => 'Insultos sobre apariencia o características físicas',
        'severity' => 'medium',
        'examples' => ['feo', 'gordo', 'flaco'],
    ],
    'discapacidad' => [
        'name' => 'Discapacidad',
        'description' => 'Insultos relacionados con discapacidades',
        'severity' => 'high',
        'examples' => ['jorobado', 'cojo', 'ciego'],
    ],
    'genero' => [
        'name' => 'Género',
        'description' => 'Insultos basados en género o identidad sexual',
        'severity' => 'high',
        'examples' => ['marica', 'puta', 'maricon'],
    ],
    'religioso' => [
        'name' => 'Religioso',
        'description' => 'Insultos relacionados con religión',
        'severity' => 'high',
        'examples' => ['hereje', 'sacrilegio'],
    ],
    'etnico' => [
        'name' => 'Étnico',
        'description' => 'Insultos basados en etnia u origen',
        'severity' => 'high',
        'examples' => ['negro', 'indio', 'gitano'],
    ],
    'fonetico' => [
        'name' => 'Fonético',
        'description' => 'Nombre y apellido, leídos seguidos y sin pausa, componen una palabra ' .
            'u otro término distinto (a menudo vulgar) que no está presente en ninguno de los ' .
            'dos por separado',
        'severity' => 'medium',
        'examples' => ['Elba Gina → "el vagina"', 'Felipe Lotas → "Feli-pelotas"', 'Susana Oria → "su zanahoria"'],
    ],
];
