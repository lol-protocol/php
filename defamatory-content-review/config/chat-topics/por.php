<?php

/** Portuguese chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'por', 'kind' => 'chat-topics'],
    'everyday' => ['louça', 'ver-me'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'boquete', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbacao', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbar', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'peitos', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intima', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['fotos intimas']],
            ['word' => 'sexo', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sexo oral', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'estupro', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'estuprar', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardeio', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'missil', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massacre', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massacrar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'exterminar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'atentado', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasao', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'exercito', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'fuzilar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'degolar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'limpeza etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'transar', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bvou\s+(?:te\s+)?(?:matar|degolar|estripar|esfaquear)\b|\bte\s+(?:mato|degolo|estripo|esfaqueio)\b|\bmatar\s+voce\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'ameaca'],
        ['pattern' => '\bvou\s+te\s+estuprar\b|\bte\s+estupro\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'ameaca sexual'],
    ],
];
