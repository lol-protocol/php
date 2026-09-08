<?php

// Portuguese - Português
return [
    'insultos_pessoais' => [
        ['word' => 'idiota', 'riskType' => 'intelectual', 'severity' => 'medium'],
        ['word' => 'imbecil', 'riskType' => 'intelectual', 'severity' => 'medium'],
        ['word' => 'estupido', 'riskType' => 'intelectual', 'severity' => 'medium'],
        ['word' => 'retardado', 'riskType' => 'intelectual', 'severity' => 'high'],
        ['word' => 'porco', 'riskType' => 'animal', 'severity' => 'medium'],
        ['word' => 'burro', 'riskType' => 'animal', 'severity' => 'medium'],
        ['word' => 'besta', 'riskType' => 'animal', 'severity' => 'medium'],
    ],
    'insultos_morais' => [
        ['word' => 'bastardo', 'riskType' => 'moral', 'severity' => 'high'],
        ['word' => 'filho da puta', 'riskType' => 'moral', 'severity' => 'high'],
        ['word' => 'canalha', 'riskType' => 'moral', 'severity' => 'high'],
        ['word' => 'criminoso', 'riskType' => 'moral', 'severity' => 'high'],
        ['word' => 'assassino', 'riskType' => 'moral', 'severity' => 'high'],
    ],
    'insultos_corpo' => [
        ['word' => 'corcunda', 'riskType' => 'discapacidad', 'severity' => 'high'],
        ['word' => 'aleijado', 'riskType' => 'discapacidad', 'severity' => 'high'],
        ['word' => 'cego', 'riskType' => 'discapacidad', 'severity' => 'medium'],
        ['word' => 'surdo', 'riskType' => 'discapacidad', 'severity' => 'medium'],
    ],
    'insultos_genero' => [
        ['word' => 'viado', 'riskType' => 'genero', 'severity' => 'high'],
        ['word' => 'puta', 'riskType' => 'genero', 'severity' => 'high'],
        ['word' => 'vagabunda', 'riskType' => 'genero', 'severity' => 'high'],
    ],
    'palavras_vulgares' => [
        ['word' => 'merda', 'riskType' => 'ordinario', 'severity' => 'high'],
        ['word' => 'bosta', 'riskType' => 'ordinario', 'severity' => 'high'],
        ['word' => 'caralho', 'riskType' => 'ordinario', 'severity' => 'high'],
    ],
];
