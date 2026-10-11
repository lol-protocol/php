<?php

/** Portuguese chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'por', 'kind' => 'chat-topics'],
    'everyday' => ['louça', 'ver-me', 'pôr-nos'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'boquete', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbacao', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'peitos', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intima', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['fotos intimas']],
            ['word' => 'sexo', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sexo oral', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'estupro', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'estuprar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardeio', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'missil', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacrar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'exterminar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'atentado', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'invasao', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'exercito', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'fuzilar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'degolar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'limpeza etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'transar', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bvou\s+(?:te\s+)?(?:matar|degolar|estripar|esfaquear)\b|\bte\s+(?:mato|degolo|estripo|esfaqueio)\b|\bmatar\s+voce\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'ameaca'],
        ['pattern' => '\bvou\s+te\s+estuprar\b|\bte\s+estupro\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'ameaca sexual'],
    ],
];
